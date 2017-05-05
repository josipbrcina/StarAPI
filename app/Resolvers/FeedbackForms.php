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
        $formFields = GenericModel::whereTo('feedback-forms')
            ->first();
        $resolved = $formFields->getAttribute('fields');

        return $resolved;
    }
}
