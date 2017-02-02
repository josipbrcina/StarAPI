<?php

namespace TheShop\Src\Listeners;

use \App\Profile;
use \App\Helpers\MailSend;

class ProfileUpdate
{
    /**
     * Handle the event
     * @param \TheShop\Src\Listeners\ProfileUpdate $event
     */
    public function handle(\TheShop\Src\Listeners\ProfileUpdate $event)
    {
        $profileChanges = $event->profile->getDirty();
        $profileId = $event->profile->_id;

        if (key_exists('xp', $profileChanges)) {
            // Send email with XP status changed
            $oldProfile = Profile::find($profileId);
            $oldXp = $oldProfile->xp;
            $xpDifference = $event->profile->xp - $oldXp;
            $emailMessage = \Config::get('sharedSettings.internalConfiguration.profile_update_xp_message');
            $data = [
                'xpDifference' => $xpDifference,
                'emailMessage' => $emailMessage
            ];
            $view = 'emails.xp-change';
            $subject = 'Xp status changed!';

            MailSend::send($view, $data, $event->profile, $subject);

            if (!empty($event->profile->slack)) {
                //Send slack message with XP status changed
                $recipient = '@' . $event->profile->slack;
                $message = str_replace('{N}', ($xpDifference > 0 ? "+" . $xpDifference : $xpDifference), $emailMessage);
                \SlackChat::message($recipient, $message);
            }
        }
    }
}
