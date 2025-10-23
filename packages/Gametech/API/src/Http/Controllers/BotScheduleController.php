<?php

namespace Gametech\API\Http\Controllers;

use Carbon\Carbon;
use Gametech\API\Models\ScheduleMessage;
use Gametech\API\Models\ScheduleMessageProxy;
use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
class BotScheduleController extends AppBaseController
{
    public function show(Request $request){

        $response = Telegram::bot('announce')->getMe();
        return $response;
    }
    public function handleUpdate(Request $request)
    {
        $telegram = new Api('7723908071:AAHQCl0hTTG4nnDkzRWhYFUKcseJBk7gx9E');

        $update = $telegram->setTimeOut(5)->setConnectTimeOut(5)->commandsHandler(true);
        if ($update->isType('message')) {
            $path = storage_path('logs/bot/webhook_schedule_' . now()->format('Y_m_d') . '.log');
            file_put_contents($path, print_r($update, true), FILE_APPEND);


            $message = $update->getMessage();

            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            $userId = $message->getFrom()->getId();


            $currentStep = ScheduleMessage::on('mysqlbot')->where('user_id', $userId)->latest()->first();


            if ($text == 'เพิ่มประกาศ') {
                if (! $currentStep || $currentStep->step == 'completed') {
                    $newSchedule = new ScheduleMessage;
                    $newSchedule->setConnection('mysqlbot');
                    $newSchedule->chat_id = $chatId;
                    $newSchedule->user_id = $userId;
                    $newSchedule->step = 'date';
                    $newSchedule->save();
                    Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Please send me the date and time (YYYY-MM-DD HH:MM).',
                    ]);
                }

            }else{
                if (! $currentStep) {
                    return "ok";

                } else if ($currentStep->step == 'date') {
                    // Save date and ask for media
                    $currentStep->scheduled_time = Carbon::createFromFormat('Y-m-d H:i', $text);
                    $currentStep->step = 'media';
                    $currentStep->save();

                    Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Please send the media URL or file.',
                    ]);
                } elseif ($currentStep->step == 'media') {
                    $fileId = '';
                    if ($update->getMessage()->has('document')) {
                        $video = $update->getMessage()->getDocument();
                        $fileId = $video->getFileId();
                        $currentStep->message = $message->getCaption();

                        if($update->getMessage()->has('caption_entities')){
                            $entities = $message->get('caption_entities');
                            $currentStep->entities = $entities;
                            foreach($entities as $entity){
                                $emojiData = [
                                    'emoji_type' => $entity['type'],
                                    'offset' => $entity['offset'],
                                    'length' => $entity['length'],
                                    'custom_emoji_id' => $entity['custom_emoji_id']
                                ];

                                $currentStep->emojis()->create($emojiData);
                            }
                        }

                    }else{
                        $currentStep->message = $text;
                    }
                    $currentStep->media_url = $fileId;

                    $currentStep->step = 'completed';
                    $currentStep->save();

                    if($fileId){
                        Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendVideo([
                            'chat_id' => $chatId,
                            'video' => $fileId,
                            'caption' => $message->getCaption(),
                        ]);
                    }else {
                        Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendMessage([
                            'chat_id' => $chatId,
                            'text' => $text,
                        ]);
                    }

                    Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Your message schedule is complete and will be sent accordingly",
                    ]);

                } elseif ($currentStep->step == 'message') {
                    // Complete conversation
                    $currentStep->message = $text;
                    $currentStep->step = 'completed';
                    $currentStep->save();

                    Telegram::bot('announce')->setTimeOut(5)->setConnectTimeOut(5)->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Your message schedule is complete and will be sent accordingly!',
                    ]);
                }
            }


        }
        return 'ok';
    }
}
