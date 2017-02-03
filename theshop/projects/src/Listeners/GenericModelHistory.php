<?php

namespace TheShop\Projects\Listeners;

class GenericModelHistory
{
    /**
     * Handle the event.
     * @param \TheShop\Projects\Events\GenericModelHistory $event
     */
    public function handle(\TheShop\Projects\Events\GenericModelHistory $event)
    {
        if ($event->model->isDirty()) {
            $newAllAttributes = $event->model->getAttributes();
            $newValues = $event->model->getDirty();
            $oldValues = $event->model->getOriginal();

            $history = $event->model->history;
            $date = new \DateTime();
            $unixTime = $date->format('U');
            foreach ($newValues as $newField => $newValue) {
                if (key_exists($newField, $oldValues)) {
                    $history[] = [
                        'profileId' => \Auth::user()->id,
                        'filedName' => $newField,
                        'oldValue' => $oldValues[$newField],
                        'newValue' => $newValue,
                        'timestamp' => (int) ($unixTime . '000') // Microtime
                    ];
                } else {
                    $history[] = [
                        'profileId' => \Auth::user()->id,
                        'fieldName' => $newField,
                        'oldValue' => null,
                        'newValue' => $newValue,
                        'timestamp' => (int) ($unixTime . '000')
                    ];
                }
            }

            foreach ($oldValues as $oldFieldName => $oldFieldValue) {
                if (!key_exists($oldFieldName, $newAllAttributes)) {
                    $history[] = [
                        'profileId' => \Auth::user()->id,
                        'fieldName' => $oldFieldName,
                        'oldValue' => $oldFieldValue,
                        'newValue' => null,
                        'timestamp' => (int) ($unixTime . '000')
                    ];
                }
            }

            $event->model->history = $history;
        }
    }
}
