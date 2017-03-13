<?php

namespace App\Console\Commands;

use App\Helpers\Slack;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Class MonthlyMinimumCheck
 * @package App\Console\Commands
 */
class MonthlyMinimumCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee:minimum:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check employees\' monthly minimum';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profiles = Profile::all();

        $admins = [];
        foreach ($profiles as $profile) {
            if ($profile->admin === true) {
                $admins[] = $profile;
            }
        }

        foreach ($profiles as $profile) {
            if (!$profile->employee === true) {
                continue;
            }

            $profilePerformance = new ProfilePerformance();
            $dateStart = new \DateTime();
            $unixStart = $dateStart->modify('first day of last month')->format('U');

            $dateEnd = new \DateTime();
            $unixEnd = $dateEnd->modify('last day of last month')->format('U');

            $performance = $profilePerformance->aggregateForTimeRange($profile, $unixStart, $unixEnd);

            $realPayoutCombined = $performance['realPayoutCombined'];
            $rolesDefinition = Config::get('sharedSettings.internalConfiguration.employees.roles');

            $requiredMinimum = $rolesDefinition[$profile->employeeRole]['minimumEarnings'];

            // Check if minimum missed
            if ($realPayoutCombined < $requiredMinimum) {
                // Update profile
                $profile->minimumsMissed++;
                $profile->save();

                // Format messages
                $minimumDiff = $requiredMinimum - $realPayoutCombined;
                $userMessage = 'Hey, you\'ve just missed monthly minimum for your role by: *'
                    . $minimumDiff
                    . '*. Total monthly minimums missed: *'
                    . $profile->minimumsMissed
                    . '*';

                $adminMessage = 'Hey, *' . $profile->name
                    . '* (ID: '
                    . $profile->id
                    . ') missed their monthly minimum by *'
                    . $minimumDiff
                    . '*. Total monthly minimums missed: *'
                    . $profile->minimumsMissed
                    . '*';

                // Notify employee
                if ($profile->slack && $profile->active) {
                    $recipient = '@' . $profile->slack;
                    Slack::sendMessage($recipient, $userMessage, Slack::MEDIUM_PRIORITY);
                }

                // Notify admins
                foreach ($admins as $admin) {
                    if ($admin->slack && $admin->active) {
                        $recipient = '@' . $admin->slack;
                        Slack::sendMessage($recipient, $adminMessage, Slack::MEDIUM_PRIORITY);
                    }
                }
            }
        }
    }
}
