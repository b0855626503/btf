<?php

namespace Gametech\Auto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PointClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:point';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Point';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        DB::table('members')->update(['point_deposit' => 0]);
    }
}
