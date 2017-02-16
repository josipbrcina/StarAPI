<?php

namespace Tests\Collections;

use App\GenericModel;

trait ProfileRelated
{
    protected $profile = null;

    /**
     * Get profile XP record
     * @return GenericModel
     */
    public function getXpRecord()
    {
        $oldCollection = GenericModel::getCollection();
        GenericModel::setCollection('xp');

        $profileXp = new GenericModel(['records' => []]);
        $profileXp->save();
        $this->profile->xp_id = $profileXp->_id;

        GenericModel::setCollection($oldCollection);

        return $profileXp;
    }

    /**
     * Adds new XP record
     * @param GenericModel $xpRecord
     * @param null $timestamp
     * @return GenericModel
     */
    public function addXpRecord(GenericModel $xpRecord, $timestamp = null)
    {
        if (!$timestamp) {
            $time = new \DateTime();
            $timestamp = $time->format('U');
        }

        $records = $xpRecord->records;
        $records[] = [
            'xp' => 1,
            'details' => 'Testing XP records.',
            'timestamp' => $timestamp
        ];
        $xpRecord->records = $records;
        $xpRecord->save();

        return $xpRecord;
    }
}
