<?php

namespace App\Http\Controllers;

use App\Exceptions\FileUploadException;
use App\GenericModel;
use App\Helpers\AuthHelper;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests;

class FileUploadController extends Controller
{
    public function uploadFile(Request $request)
    {
        $account = AuthHelper::getAuthenticatedUser();
        $user = Profile::find($account->_id);
        $userId = $user->_id;

        if ($request->has('projectId')) {
            $projectId = $request->get('projectId');
        } else {
            $projectId = null;
        }

        $files = $request->file();

        $response = [];
        foreach ($files as $file) {
            if ($file->getError()) {
                throw new FileUploadException('File could not be uploaded.');
            }

            $upload = GenericModel::createModel([], 'uploads');

            $fileName = $userId . '-' . str_random(20) . '.' . $file->getClientOriginalExtension();

            $s3 = Storage::disk('s3');
            $filePath = $fileName;
            $s3->put($filePath, file_get_contents($file), 'public');

            $fileUrl = Storage::cloud()->url($fileName);

            $upload->projectId = $projectId;
            $upload->name = $file->getClientOriginalName();
            $upload->fileUrl = $fileUrl;
            $upload->save();

            $response[] = $upload;
        }

        return $this->jsonSuccess($response);
    }

    /**
     * Lists all uploaded files with set projectId
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUploads(Request $request)
    {
        $project = GenericModel::whereTo('projects')->find($request->route('id'));
        if (!$project) {
            return $this->jsonError(['Project with given ID not found'], 404);
        }

        $uploads = GenericModel::whereTo('uploads')
            ->where('projectId', '=', $request->route('id'))
            ->get();

        return $this->jsonSuccess($uploads);
    }


    /**
     * Deletes uploaded files
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProjectUploads(Request $request)
    {
        $project = GenericModel::whereTo('projects')->find($request->route('projectId'));
        if (!$project) {
            return $this->jsonError(['Project with given ID not found'], 404);
        }

        $uploads = GenericModel::whereTo('uploads')
            ->where('projectId', '=', $request->route('projectId'))
            ->get();

        foreach ($uploads as $upload) {
            $upload->delete();
        }

        return $this->jsonSuccess($uploads);
    }
}
