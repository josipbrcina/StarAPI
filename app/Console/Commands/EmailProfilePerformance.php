<?php

namespace App\Console\Commands;

use App\Helpers\MailSend;
use App\Helpers\ProfileOverall;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

/**
 * Class EmailProfilePerformance
 * @package App\Console\Commands
 */
class EmailProfilePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:profile:performance {daysAgo : How many days before time of command execution} 
    {--accountants : If option is passed, value is true, send email to admins and accountants with salary report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates user performance and sends out emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $performance = new ProfilePerformance();

        $profiles = Profile::where('employee', '=', true)->get();

        // Set time range
        $daysAgo = (int) $this->argument('daysAgo');
        $unixNow = (int) Carbon::now()->format('U');
        $unixAgo = $unixNow - $daysAgo * 24 * 60 * 60;

        $forAccountants = $this->option('accountants');

        // If option is passed set date range from 1st day of current month until last day of current month
        if ($forAccountants) {
            $unixNow = (int) Carbon::now()->endOfMonth()->format('U');
            $unixAgo = (int) Carbon::now()->firstOfMonth()->format('U');
        }

        $adminAggregation = [];

        foreach ($profiles as $profile) {
            if ($forAccountants && !$profile->employee) {
                continue;
            }

            $data = $performance->aggregateForTimeRange($profile, $unixAgo, $unixNow);
            $data['name'] = $profile->name;
            $data['fromDate'] = Carbon::createFromFormat('U', $unixAgo)->format('Y-m-d');
            $data['toDate'] = Carbon::createFromFormat('U', $unixNow)->format('Y-m-d');

            // If option is not passed send mail to each profile
            if ($forAccountants === false) {
                $view = 'emails.profile.performance';
                $subject = Config::get('mail.private_mail_subject');

                if ($profile->email && $profile->active) {
                    MailSend::send($view, $data, $profile, $subject);
                }
            }
            $adminAggregation[] = $data;

            if ($forAccountants) {
                // Update profile overall stats
                $profileOverall = ProfileOverall::getProfileOverallRecord($profile);
                $profileOverall->totalCost += $data['costTotal'];
                $profileOverall->profit = $profileOverall->totalEarned - $profileOverall->totalCost;
                $profileOverall->save();
            }
        }

        $overviewRecipients = Profile::where('admin', '=', true)->get();

        // If option is passed get all admins and profiles with accountant role
        if ($forAccountants) {
            $overviewRecipients = Profile::where('admin', '=', true)
                ->orWhere('role', '=', 'accountant')
                ->get();
        }

        foreach ($overviewRecipients as $recipient) {
            $view = $this->option('accountants') ? 'emails.profile.salary-performance-report'
                : 'emails.profile.admin-performance-report';
            $subject = Config::get('mail.admin_performance_email_subject');

            if ($recipient->active) {
                // Create pdf with salary report and attach it to email
                $attachments = [];
                $pdf = \PDF::loadView($view, [
                    'reports' => $adminAggregation,
                    'pdf' => true
                ])->output();
                $attachments['SalaryReport.pdf'] = $pdf;

                MailSend::send($view, ['reports' => $adminAggregation], $recipient, $subject, $attachments);
            }
        }
    }
}
