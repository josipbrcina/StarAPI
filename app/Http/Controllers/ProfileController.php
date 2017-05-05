<?php

namespace App\Http\Controllers;

use App\Events\ProfileUpdate;
use App\GenericModel;
use App\Helpers\AuthHelper;
use App\Helpers\DatabaseSeeder;
use App\Helpers\InputHandler;
use App\Services\ProfilePerformance;
use App\Profile;
use Illuminate\Http\Request;
use App\Helpers\Configuration;
use App\Helpers\MailSend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Class ProfileController
 * @package App\Http\Controllers
 */
class ProfileController extends Controller
{
    /**
     * Returns current user if there, otherwise HTTP 401
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function current()
    {
        $account = AuthHelper::getAuthenticatedUser();
        $profile = Profile::find($account->_id);

        if (!$profile) {
            return $this->jsonError('User not logged in.', 401);
        }

        return $this->jsonSuccess($profile);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $profiles = Profile::all();
        return $this->jsonSuccess($profiles);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformance(Request $request)
    {
        // Default last month
        $lastMonthUnixStart = strtotime('first day of last month');
        $lastMonthUnixEnd = strtotime('last day of last month');

        $startDate = InputHandler::getUnixTimestamp($request->input('unixStart', $lastMonthUnixStart));
        $endDate = InputHandler::getUnixTimestamp($request->input('unixEnd', $lastMonthUnixEnd));

        $profile = Profile::find($request->route('id'));
        if (!$profile) {
            return $this->jsonError(
                ['Profile not found'],
                404
            );
        }

        $performance = new ProfilePerformance();

        return $this->jsonSuccess($performance->aggregateForTimeRange($profile, $startDate, $endDate));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformancePerTask(Request $request)
    {
        $task = GenericModel::whereTo('tasks')->find($request->route('taskId'));

        if (!$task) {
            return $this->jsonError(
                ['Task not found'],
                404
            );
        }

        $performance = new ProfilePerformance();

        return $this->jsonSuccess($performance->perTask($task));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeedback(Request $request)
    {
        $feedback = GenericModel::whereTo('feedbacks')
            ->where('userId', '=', $request->route('id'))
            ->get();

        // Check if collection has got any models returned
        if (count($feedback) > 0) {
            return $this->jsonSuccess($feedback);
        }

        return $this->jsonError(
            ['No feedback found.'],
            404
        );
    }

    /**
     * @param Request $request
     * @return GenericModel|\Illuminate\Http\JsonResponse
     */
    public function storeFeedback(Request $request)
    {
        $profile = Profile::find($request->route('id'));

        // Check if user id exists
        if (!$profile) {
            return $this->jsonError(
                ['Profile not found'],
                404
            );
        }

        $requestFields = $request->all();
        if (empty($requestFields)) {
            $requestFields = [];
        }

        // Validate request fields
        $allowedFields = [
            'createdAt',
            'answers'
        ];

        $validateFields = array_diff_key($requestFields, array_flip($allowedFields));

        if (!empty($validateFields)
            || !key_exists('createdAt', $requestFields)
            || !key_exists('answers', $requestFields)
        ) {
            return $this->jsonError('Invalid input. Request must have two fields - createdAt and answers');
        }

        $errors = [];

        if (is_array($requestFields['createdAt'])) {
            $errors[] = 'Invalid input format. createdAt field must not be type of array.';
        }

        if (!is_array($requestFields['answers'])) {
            $errors[] = 'Invalid input format. answers field must be type of array';
        }

        if (count($errors) > 0) {
            return $this->jsonError($errors);
        }

        // Validate createdAt field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['createdAt']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on createdAt field.');
        }

        // After all validations let's save model to feedbacks collection
        $feedback = new GenericModel(
            [
                'userId' => $request->route('id'),
                'createdAt' => $requestFields['createdAt'],
                'answers' => $requestFields['answers']
            ]
        );

        if ($feedback->saveModel('feedbacks')) {
            return $this->jsonSuccess($feedback);
        }

        return $this->jsonError('Issue with saving feedback.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $authenticatedUser = AuthHelper::getAuthenticatedUser();

        if (in_array($request->route('appName'), $authenticatedUser->applications)) {
            return $this->jsonError('Permission denied. Already member of this application.', 403);
        }

        $profile = Profile::find($authenticatedUser->_id);

        if ($profile && $profile->accountActive === true) {
            return $this->jsonError('Permission denied. Profile already exists in this application.', 403);
        }

        if ($profile && $profile->accountActive === false) {
            $profileToSave = $profile;
            $profileToSave->accountActive = true;
            $profileToSave->save();
        }

        if (!$profile) {
            $fields = $request->all();
            $this->validateInputsForResource($fields, 'profiles');

            $profileToSave = Profile::createForAccount($authenticatedUser->_id, $fields);

            // Send confirmation E-mail upon profile creation on the application
            $data = [
                'name' => $profileToSave->name,
                'email' => $profileToSave->email,
                'github' => $profileToSave->github,
                'trello' => $profileToSave->trello,
                'slack' => $profileToSave->slack,
            ];
            $view = 'emails.application-joined';
            $subject = 'Welcome to new application!';

            MailSend::send($view, $data, $profileToSave, $subject);
        }

        // Update account model
        $account = GenericModel::whereTo('accounts', 'accounts')
            ->find($authenticatedUser->_id);
        $applicationsArray = $account->applications;
        $applicationsArray[] = $request->route('appName');
        $account->applications = $applicationsArray;
        $account->saveModel('accounts', 'accounts');

        return $this->jsonSuccess($profileToSave);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));
        if (!$profile) {
            return $this->jsonError('User not found.', 404);
        }
        return $this->jsonSuccess($profile);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));

        if (!$profile instanceof Profile) {
            return $this->jsonError('Model not found.', 404);
        }

        if ($profile->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $fields = $request->all();
        $this->validateInputsForResource($fields, 'profiles', null, ['email' => 'required|email']);

        $profile->fill($fields);

        if ($profile->isDirty()) {
            event(new ProfileUpdate($profile));
        }

        $profile->save();

        return $this->jsonSuccess($profile);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));

        if (!$profile instanceof Profile) {
            return $this->jsonError('User not found.', 404);
        }

        if ($profile->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $profile->delete();
        return $this->jsonSuccess(['id' => $profile->id]);
    }

    /**
     * Create new record for profile vacations
     * @param Request $request
     * @return GenericModel|\Illuminate\Http\JsonResponse
     */
    public function vacation(Request $request)
    {
        $profile = Profile::find($request->route('id'));
        if (!$profile) {
            return $this->jsonError(['Profile ID not found.'], 404);
        }

        $model = GenericModel::whereTo('vacations')->find($request->route('id'));
        if ($model) {
            return $this->jsonError(['Method not allowed. Model already exists.'], 403);
        }

        $requestFields = $request->all();
        if (empty($requestFields)) {
            $requestFields = [];
        }

        // Validate request fields
        $allowedFields = [
            'dateFrom',
            'dateTo'
        ];

        $validateFields = array_diff_key($requestFields, array_flip($allowedFields));

        if (!empty($validateFields)
            || !key_exists('dateFrom', $requestFields)
            || !key_exists('dateTo', $requestFields)
        ) {
            return $this->jsonError('Invalid input. Request must have two fields - dateFrom and dateTo');
        }

        $errors = [];

        if (is_array($requestFields['dateFrom'])) {
            $errors[] = 'Invalid input format. dateFrom field must not be type of array.';
        }

        if (is_array($requestFields['dateTo'])) {
            $errors[] = 'Invalid input format. dateTo field must not be type of array';
        }

        if (count($errors) > 0) {
            return $this->jsonError($errors);
        }

        // Validate dateFrom field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['dateFrom']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on dateFrom field.');
        }

        // Validate dateTo field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['dateTo']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on dateTo field.');
        }

        $fields = [
            'records' => [
                [
                    'dateFrom' => $requestFields['dateFrom'],
                    'dateTo' => $requestFields['dateTo'],
                    'recordTimestamp' => (int)Carbon::now()->format('U')
                ]
            ]
        ];

        if ($this->validateInputsForResource($fields, 'vacations') === false) {
            return $this->jsonError(['Insufficient permissions.'], 403);
        }

        // Set model id same as profile id
        $model = new GenericModel($fields);
        $model->_id = $profile->_id;

        if ($model->saveModel('vacations')) {
            return $model;
        }

        return $this->jsonError('Issue with saving resource.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function leaveApplication(Request $request)
    {
        $authenticatedUser = AuthHelper::getAuthenticatedUser();

        if (!in_array($request->route('appName'), $authenticatedUser->applications)) {
            return $this->jsonError('Permission denied. Not a member of this application.', 403);
        }

        $profile = Profile::find($authenticatedUser->_id);

        if ($profile && $profile->accountActive === false) {
            return $this->jsonError('Permission denied. Profile already left this application.', 403);
        }

        // Add tag that profile is removed from application
        $profile->accountActive = false;
        $profile->save();

        // Update account model
        $account = GenericModel::whereTo('accounts', 'accounts')
            ->find($authenticatedUser->_id);
        $applicationsArray = $account->applications;
        $applicationsArray = array_diff($applicationsArray, [$request->route('appName')]);
        $account->applications = $applicationsArray;
        $account->saveModel('accounts', 'accounts');

        return $this->jsonSuccess('You have successfully left application.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createApplication(Request $request)
    {
        $requestedAppName = strtolower($request->get('appName'));
        if (empty($requestedAppName)) {
            return $this->jsonError('Missing appName field.', 403);
        }

        // Get list of all databases
        $listExistingDatabases = DB::connection('mongodbAdmin')->command(['listDatabases' => true]);

        $databaseNames = [];
        foreach ($listExistingDatabases as $dbResult) {
            $databasesBsonList = $dbResult->databases->getArrayCopy();
            foreach ($databasesBsonList as $dbInfo) {
                $databaseNames[] = $dbInfo->name;
            }
        }
        // Check is application already exists with requested name
        if (in_array($requestedAppName, $databaseNames)) {
            return $this->jsonError('Permission denied. Application with that name already exists.', 403);
        }

        $authenticatedUser = AuthHelper::getAuthenticatedUser();

        $fields = $request->all();
        $fields['name'] = $authenticatedUser->name;
        $fields['email'] = $authenticatedUser->email;

        $this->validateInputsForResource($fields, 'profiles');

        // Set database to requested application name
        AuthHelper::setDatabaseConnection($requestedAppName);
        DatabaseSeeder::seedApplicationDatabase();

        $profileToSave = Profile::createForAccount($authenticatedUser->_id, $fields);
        $profileToSave->admin = true;
        $profileToSave->save();

        // Send confirmation E-mail upon profile creation on the application

        $data = [
            'name' => $profileToSave->name,
            'email' => $profileToSave->email,
            'appName' => $requestedAppName
        ];

        $view = 'emails.application-created';
        $subject = 'New application created!';

        MailSend::send($view, $data, $profileToSave, $subject);

        // Update account model
        $account = GenericModel::whereTo('accounts', 'accounts')
            ->find($authenticatedUser->_id);
        $applicationsArray = $account->applications;
        $applicationsArray[] = $requestedAppName;
        $account->applications = $applicationsArray;
        $account->saveModel('accounts', 'accounts');

        return $this->jsonSuccess('Successfully created new application.');
    }
}
