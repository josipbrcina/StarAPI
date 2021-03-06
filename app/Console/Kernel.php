<?php

namespace App\Console;

use App\Helpers\WorkDays;
use Aws\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;
use App\Helpers\LastWorkDay;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SprintReminderForUnassignedTasks::class,
        Commands\XpDeduction::class,
        Commands\UnfinishedTasks::class,
        Commands\EmailProfilePerformance::class,
        Commands\MonthlyMinimumCheck::class,
        Commands\NotifyProjectParticipantsAboutTaskDeadline::class,
        Commands\SlackSendMessages::class,
        Commands\UpdateTaskPriority::class,
        Commands\NotifyAdminsTaskPriority::class,
        Commands\NotifyAdminsQaWaitingTasks::class,
        Commands\NotifyAdminsAndPoAboutLateAndQaTasks::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('sprint:remind:unassigned:tasks')
            ->twiceDaily(8, 14);

        $schedule->command('xp:activity:auto-deduct')
            ->dailyAt('13:00');

        $schedule->command('unfinished:tasks:auto-move')
            ->dailyAt('00:01');

        $schedule->command('email:profile:performance 7')
            ->weekly()
            ->mondays()
            ->at('08:00');

        $schedule->command('email:profile:performance 0 --accountants')
            ->dailyAt('16:00')
            ->when(function () {
                $workDays = WorkDays::getWorkDays(Carbon::now()->format('U'));
                $lastWorkDay = end($workDays);
                return Carbon::parse($lastWorkDay)->isToday();
            });

        $schedule->command('employee:minimum:check')
            ->monthlyOn(1, '08:00');

        $schedule->command('ping:projectParticipants:task:deadline')
            ->dailyAt('09:00');

        // Check for messages to send every minute
        $schedule->command('slack:send-messages');

        // Check task deadline and update priority
        $schedule->command('update:task:priority')
            ->dailyAt('07:00');

        // Check task deadlines based on priority and ping admins
        $schedule->command('ping:admins:task:priority')
            ->dailyAt('07:00');

        // Ping admins about tasks with Qa in progress
        $schedule->command('ping:admins:qa:waiting:tasks')
            ->twiceDaily(9, 14);

        // Ping admins and project owners about late tasks and Qa waiting tasks
        $schedule->command('ping:admins:late-and-qa-tasks')
            ->dailyAt('08:00');
    }
}
