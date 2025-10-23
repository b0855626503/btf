<?php

namespace Gametech\Auto\Jobs;


use Gametech\Admin\Models\Admin;
use Gametech\Game\Models\User;
use Gametech\Member\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class NotifyUserOfCompletedExport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $filename;

    public function __construct(Admin $user, $filename)
    {
        $this->user = $user;
        $this->filename = $filename;
    }

    public function handle()
    {
        auth()->user()->notify(new ExportReady());
    }
}
