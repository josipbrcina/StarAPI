<?php

namespace App\Resolvers;

use App\GenericModel;

/**
 * Class FeedbackForms
 * @package App\Resolvers
 */
class FeedbackForms
{
    /**
     * Resolve feedbackForms
     * @return mixed
     */
    public static function getForms()
    {
        $preSetCollection = GenericModel::getCollection();
        GenericModel::setCollection('feedback-forms');

        $formFields = GenericModel::first();
        $resolved = $formFields->getAttribute('fields');

        GenericModel::setCollection($preSetCollection);

        return $resolved;
    }
}
