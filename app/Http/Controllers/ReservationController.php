<?php

namespace App\Http\Controllers;

use App\GenericModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Events\GenericModelUpdate;

/**
 * Handles project reservation logic
 *
 * Class ReservationController
 * @package App\Http\Controllers
 */
class ReservationController extends Controller
{
    const MAKE_RESERVATION = true;
    const ACCEPT_OR_DECLINE = false;

    /**
     * Make reservation for selected project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function makeProjectReservation(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($project, $errors, $time, self::MAKE_RESERVATION) === false) {
            return $this->jsonError($errors, 403);
        }


        $reservationsBy = $project->reservationsBy;
        $reservationsBy[] = ['user_id' => Auth::user()->id, 'timestamp' => $time];
        $project->reservationsBy = $reservationsBy;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Accept reserved project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function acceptProject(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($project, $errors, $time, self::ACCEPT_OR_DECLINE) === false) {
            return $this->jsonError($errors, 403);
        }

        $project->acceptedBy = Auth::user()->id;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Decline reserved project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function declineProject(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($project, $errors, $time, self::ACCEPT_OR_DECLINE) === false) {
            return $this->jsonError($errors, 403);
        }

        $declined = $project->declinedBy;
        $declined[] = ['user_id' => \Auth::user()->id, 'timestamp' => $time];
        $project->declinedBy = $declined;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Make reservation for selected task
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function makeTaskReservation(Request $request)
    {
        GenericModel::setCollection('tasks');
        $task = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($task, $errors, $time, self::MAKE_RESERVATION) === false) {
            return $this->jsonError($errors, 403);
        }

        $reservationsBy = $task->reservationsBy;
        $reservationsBy[] = ['user_id' => Auth::user()->id, 'timestamp' => $time];
        $task->reservationsBy = $reservationsBy;

        event(new GenericModelUpdate($task));

        if ($task->save()) {
            return $task;
        }

        return $this->jsonError('Issue with updating resource.');
    }

    /**
     * Accept reserved task
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function acceptTask(Request $request)
    {
        GenericModel::setCollection('tasks');
        $task = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($task, $errors, $time, self::ACCEPT_OR_DECLINE) === false) {
            return $this->jsonError($errors, 403);
        }

        $task->owner = Auth::user()->id;

        event(new GenericModelUpdate($task));

        if ($task->save()) {
            return $task;
        }

        return $this->jsonError('Issue with updating resource.');
    }

    /**
     * Decline reserved task
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function declineTask(Request $request)
    {
        GenericModel::setCollection('tasks');
        $task = GenericModel::find($request->route('id'));

        $errors = [];
        $time = (new \DateTime())->getTimestamp();

        if ($this->validateReservation($task, $errors, $time, self::ACCEPT_OR_DECLINE) === false) {
            return $this->jsonError($errors, 403);
        }

        $declined = $task->declinedBy;
        $declined[] = ['user_id' => \Auth::user()->id, 'timestamp' => $time];
        $task->declinedBy = $declined;

        event(new GenericModelUpdate($task));

        if ($task->save()) {
            return $task;
        }

        return $this->jsonError('Issue with updating resource.');
    }


    /**
     * Reservation validator - checks if project/task ID exist, checks if project/task has already been
     * accepted or declined
     * @param GenericModel|null $model
     * @param $errors
     * @return bool
     */
    private function validateReservation(GenericModel $model = null, &$errors, $time, $action)
    {
        //error messages
        $errorMessages = [
            'empty' => 'ID not found.',
            'accepted' => '%m already accepted.',
            'declined' => '%m already declined.',
            'reserved' => '%m already reserved',
            'denied' => 'Permission denied.'
        ];

        //check if passed model exists in database
        if (empty($model)) {
            $errors[] = $errorMessages['empty'];
            return false;
        }

        //check if model is already accepted
        if (!empty($model['collection'] === 'projects' ? $model->acceptedBy : $model->owner)) {
            $errors[] = $model['collection'] === 'projects' ?
                str_replace('%m', 'Project', $errorMessages['accepted'])
                : str_replace('%m', 'Task', $errorMessages['accepted']);
        }

        //check if model is already declined by current user
        if (isset($model->declinedBy)) {
            foreach ($model->declinedBy as $declined) {
                if ($declined['user_id'] === Auth::user()->id) {
                    $errors[] = $model['collection'] === 'projects' ?
                        str_replace('%m', 'Project', $errorMessages['declined'])
                        : str_replace('%m', 'Task', $errorMessages['declined']);
                    return false;
                }
            }
        }

        //read reservation time from shared settings
        if ($model['collection'] === 'projects') {
            $reservationTime = Config::get('sharedSettings.internalConfiguration.projects.reservation.maxReservationTime');
        } else {
            $reservationTime = Config::get('sharedSettings.internalConfiguration.tasks.reservation.maxReservationTime');
        }

        if (isset($model->reservationsBy)) {
            foreach ($model->reservationsBy as $reserved) {
                //for makeReservation check time if model is already reserved by anyone
                if ($action === self::MAKE_RESERVATION
                    && ($time - $reserved['timestamp'] <= ($reservationTime * 60))
                    && empty($model['collection'] === 'projects' ? $model->acceptedBy : $model->owner)
                ) {
                    $errors[] = $model['collection'] === 'projects' ?
                        str_replace('%m', 'Project', $errorMessages['reserved'])
                        : str_replace('%m', 'Task', $errorMessages['reserved']);
                }

                //check if user already reserved and time passed, if so add flag declinedBy and return error
                if ($action === (self::MAKE_RESERVATION || self::ACCEPT_OR_DECLINE)
                    && ($time - $reserved['timestamp'] >= ($reservationTime * 60))
                    && $reserved['user_id'] === Auth::user()->id
                    && empty($model['collection'] === 'projects' ? $model->acceptedBy : $model->owner)
                ) {
                    $declined = $model->declinedBy;
                    $declined[] = ['user_id' => Auth::user()->id, 'timestamp' => $time];
                    $model->declinedBy = $declined;
                    $model->save();

                    $errors[] = $model['collection'] === 'projects' ?
                        str_replace('%m', 'Project', $errorMessages['declined'])
                        : str_replace('%m', 'Task', $errorMessages['declined']);
                }

                //for accept or decline actions on already reserved project/task check time and user ID for permission
                if ($action === self::ACCEPT_OR_DECLINE
                    && ($time - $reserved['timestamp'] <= ($reservationTime * 60))
                    && ($reserved['user_id'] !== Auth::user()->id)
                ) {
                    $errors[] = $errorMessages['denied'];
                }
            }
        }
        if (count($errors) > 0) {
            return false;
        }

        return true;
    }
}
