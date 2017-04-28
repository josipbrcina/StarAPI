<?php

namespace App\Http\Controllers;

use App\Exceptions\DynamicValidationException;
use App\Helpers\AuthHelper;
use Illuminate\Http\Request;
use App\Profile;
use App\Validation;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\AclHelper;

/**
 * Class Controller
 * @package App\Http\Controllers
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $request;

    /**
     * Controller constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonSuccess($data, array $headers = [])
    {
        $headers = $this->appendAuthHeaders($headers);

        return response()->json($data, 200, $headers);
    }

    /**
     * @param $errors
     * @param int $statusCode
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonError($errors, $statusCode = 400, array $headers = [])
    {
        $response = [];
        if (!is_array($errors)) {
            $errors = [$errors];
        }

        $response['error'] = true;
        $response['errors'] = $errors;

        $headers = $this->appendAuthHeaders($headers);

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * @param array $headers
     * @return array
     */
    private function appendAuthHeaders(array $headers)
    {
        //Authenticate user
        $token = JWTAuth::getToken();
        if ($token) {
            $headers = array_merge(
                $headers,
                [
                    'Authorization' => 'bearer ' . $token
                ]
            );
        }

        return $headers;
    }

    /**
     * @param $fields
     * @param $resourceName
     * @param null $model
     * @param array $inputOverrides
     * @param bool $jwtAuthenticated
     * @return bool
     * @throws DynamicValidationException
     */
    protected function validateInputsForResource(&$fields, $resourceName, $model = null, array $inputOverrides = [], $jwtAuthenticated = true)
    {
        if ($jwtAuthenticated) {
            $user = AuthHelper::getAuthenticatedUser();
        } else {
            $user = null;
        }

        $profile = null;
        if ($user) {
            $profile = Profile::find($user->_id);
        }
        if ($profile) {
            $user = $profile;
        }

        // Validations per user role
        if ($user && $user->admin === true) {
            return true;
        }

        $validationModel = Validation::where('resource', $resourceName)
            ->first();

        if (!$validationModel instanceof Validation) {
            throw new DynamicValidationException(['Validation definition is missing for this resource.'], 500);
        }

        $validations = $validationModel->getFields();

        $acl = AclHelper::getAcl($user);
        $userRole = $acl->name;
        $updateOwn = false;

        if (isset($validationModel->acl[$userRole]['updateOwn'])) {
            $updateOwn = $validationModel->acl[$userRole]['updateOwn'];
        }

        if (isset($model->ownerId) && $model->ownerId === $user->id && $updateOwn === true) {
            return true;
        }

        // Check if validation rules exists for use role
        if (!key_exists($userRole, $validationModel->acl)) {
            throw new DynamicValidationException(['Validation rules missing for user role: ' . $userRole], 500);
        }

        $requestMethod = $this->request->method();
        switch ($requestMethod) {
            case 'POST':
            case 'GET':
            case 'DELETE':
                if (!$validationModel->acl[$userRole][$requestMethod]) {
                    throw new DynamicValidationException(['Request method ' . $requestMethod . ' not permitted.'], 403);
                }
                break;

            case 'PUT':
                $allowedFields = [];
                $editableFields = $validationModel->acl[$userRole]['editable'];
                $editableFields = array_flip($editableFields);
                foreach ($fields as $field => $value) {
                    if (isset($editableFields[$field])) {
                        $allowedFields[$field] = $value;
                    } else {
                        // TODO: throw this exception when PATCH is introduced
                        // throw new DynamicValidationException(['No permissions to edit "' . $field . '"'], 403);
                    }
                }
                $fields = $allowedFields;
                break;

            default:
                throw new DynamicValidationException(['Request method ' . $requestMethod . ' not supported.'], 501);
                break;
        }

        foreach ($inputOverrides as $field => $value) {
            $validations[$field] = $value;
        }

        $checkValidations = [];

        foreach ($validations as $field => $value) {
            if (!isset($fields[$field])) {
                continue;
            }

            $checkValidations[$field] = $value;
        }

        $validator = Validator::make(
            $fields,
            $checkValidations,
            $validationModel->getMessages()
        );

        if ($validator->fails()) {
            throw new DynamicValidationException($validator->errors()->all(), 400);
        }

        return true;
    }

    /**
     * @return Profile|null
     */
    protected function getCurrentProfile()
    {
        try {
            $profile = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            $profile = null;
        }

        return $profile;
    }
}
