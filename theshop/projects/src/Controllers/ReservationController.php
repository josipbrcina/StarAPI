<?php

namespace TheShop\Projects\Controllers;

use App\GenericModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * Handles project reservation logic
 *
 * Class ReservationController
 * @package App\Http\Controllers
 */
class ReservationController extends Controller
{
    /**
     * Make reservation for selected project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function make(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        if ($this->validateReservation($project) !== false) {
            return $this->validateReservation($project);
        }

        $date = new \DateTime();
        $time = $date->getTimestamp();
        $reservationTime = \Config::get('projectReservation.reservation_time');
        if (isset($project->reservationsBy)) {
            foreach ($project->reservationsBy as $reserved) {
                if ($time - $reserved['timestamp'] <= ($reservationTime * 60)) {
                    return $this->jsonError(['Project already reserved.'], 403);
                }
            }
        }

        $reservationsBy = $project->reservationsBy;
        $reservationsBy[] = ['user_id' => \Auth::user()->id, 'timestamp' => $time];
        $project->reservationsBy = $reservationsBy;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Accept reservation for selected project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function accept(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        if ($this->validateReservation($project) !== false) {
            return $this->validateReservation($project);
        }

        $date = new \DateTime();
        $time = $date->getTimestamp();
        $reservationTime = \Config::get('projectReservation.reservation_time');
        if (isset($project->reservationsBy)) {
            foreach ($project->reservationsBy as $reserved) {
                if (($time - $reserved['timestamp']) <= ($reservationTime * 60) && !($reserved['user_id'] == \Auth::user()->id)) {
                    return $this->jsonError(['Permission denied.'], 403);
                }
            }
        }

        $project->acceptedBy = \Auth::user()->id;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Decline reservation for selected project
     *
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function decline(Request $request)
    {
        GenericModel::setCollection('projects');
        $project = GenericModel::find($request->route('id'));

        if ($this->validateReservation($project) !== false) {
            return $this->validateReservation($project);
        }

        $date = new \DateTime();
        $time = $date->getTimestamp();
        $reservationTime = \Config::get('projectReservation.reservation_time');
        if (isset($project->reservationsBy)) {
            foreach ($project->reservationsBy as $reserved) {
                if (($time - $reserved['timestamp']) <= ($reservationTime * 60) && !($reserved['user_id'] == \Auth::user()->id)) {
                    return $this->jsonError(['Permission denied.'], 403);
                }
            }
        }

        $declined = $project->declinedBy;
        $declined[] = ['user_id' => \Auth::user()->id, 'timestamp' => $time];
        $project->declinedBy = $declined;
        $project->save();

        return $this->jsonSuccess($project);
    }

    /**
     * Reservation validator - checks if project ID exist, checks if project has already been
     * accepted or declined
     *
     * @param null $project
     * @return bool|\Illuminate\Http\JsonResponse
     */
    private function validateReservation($project = null)
    {
        if (empty($project)) {
            return $this->jsonError(['ID not found.'], 404);
        }

        if (!empty($project->acceptedBy)) {
            return $this->jsonError(['Project already accepted.'], 403);
        }

        if (isset($project->declinedBy)) {
            foreach ($project->declinedBy as $declined) {
                if ($declined['user_id'] == \Auth::user()->id) {
                    return $this->jsonError(['Project already declined.'], 403);
                }
            }
        }
        return false;
    }
}
