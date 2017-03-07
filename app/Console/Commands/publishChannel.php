<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class publishChannel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:publishChannel {--channel_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push channels';



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
        if ($channel_id = $this->option('channel_id')) {
            $this->info("Channel $channel_id");

            $channel =  DB::table('channels')->where('id',$channel_id)->get();
            if (file_exists('/lib/systemd/system/ch_'.$channel->id.'.service'))
            {
                $this->warn('Channel already exist');
            }else {
                $this->info('Generate channels config');
                $service =
                    '[Unit]
Description= Record ' . $channel->name . '
After=network.target
After=rc-local.service

[Service]
LimitNOFILE=65536
PIDFile=/var/run/' . $channel->catchupcode . '.pid
EnvironmentFile=/data/env/ch_' . $channel->id . '.conf
ExecStart=/usr/local/bin/ffmpeg $ARG1
ExecReload=/bin/kill -HUP $MAINPID
KillMode=process
Type=simple
Restart=on-failure
RestartSec=5
StartLimitInterval=60s
StartLimitBurst=3

[Install]
Alias=' . $channel->catchupcode . '.service
WantedBy=multi-user.target';


                file_put_contents('/lib/systemd/system/ch_' . $channel->id . '.service', $service);
                $env_file = 'ARG1= -re -hide_banner -loglevel error -i http://192.168.194.54:8040/Channel_2 -c copy -f ssegment -segment_time 5 /data/tv/Channel_2/%t-%l-%d.ts';
                file_put_contents('/data/env/ch_' .$channel->id . '.conf', $env_file);

                $this->info('Make working directory');
                mkdir('/data/tv'.$channel->catchupcode);

                $process = new Process('systemctl status '.$channel->catchupcode);
                $process->start();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                $this->info( $process->getOutput());


            }
        }

    }


}
