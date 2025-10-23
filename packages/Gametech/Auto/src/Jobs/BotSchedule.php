<?php

namespace Gametech\Auto\Jobs;

use Gametech\API\Models\ScheduleMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;


class BotSchedule implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 10;
    public $timeout = 10;
    public $tries = 0;
    public $maxExceptions = 3;

    protected $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function tags()
    {
        return ['render', 'telegram:' . $this->message->id];
    }

    public function uniqueId()
    {
        return $this->message->id;
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->message->id))->dontRelease()];
    }
    public function handle()
    {


            $telegram = new Api('7723908071:AAHQCl0hTTG4nnDkzRWhYFUKcseJBk7gx9E');

            $text = $this->message->message;
            $emojis = $this->message->emojis ?? [];
            if(count($emojis) > 0) {
                $text = $this->replaceEmojiWithCustomTag($text,$emojis);
            }


            $telegram->sendVideo([
                'chat_id' => $this->message->chat_id,
                'video' => $this->message->media_url,
                'caption' => $text,
                'parse_mode' => 'HTML',
                'caption_entities' => $this->message->entities
            ]);

//            $this->message->delete();

            return true;


    }

    public function replaceEmojiWithCustomTag($text, $emojis) {

//        Log::info('Emoji Collect:', ['id' => $this->message->id]);
        $emojis = $emojis->toArray();
//        dd(array_reverse($emojis));

//        $emojiss = preg_split('//u', $text, null, PREG_SPLIT_NO_EMPTY);
//
//
//        foreach ($emojiss as $index => $emoji) {
//            echo "Emoji: {$emoji}, Offset: {$index}, Length: 1\n";
//            $emojiCode = "<tg-emoji emoji-id=\"{$emoji->custom_emoji_id}\">{$emojiCharacter}</tg-emoji>";
//        }


        // กลับด้าน array เพื่อให้การแทนที่ไม่ส่งผลต่อการคำนวณ offset ของ emoji ที่ตามมา
        foreach (array_reverse($emojis) as $emoji) {
            $emojiCharacter = mb_substr($text, $emoji['offset'], $emoji['length'], "UTF-8");
//            dd($emojiCharacter);
            // สร้าง tag ที่มี custom emoji ID
            $emojiCode = "<tg-emoji emoji-id=\"{$emoji['custom_emoji_id']}\">{$emojiCharacter}</tg-emoji>";

            $before = mb_substr($text, 0, $emoji['offset'], "UTF-8");
            $after = mb_substr($text, $emoji['offset'] + $emoji['length'], null, "UTF-8");

            // รวม string
            $text = $before . $emojiCode . $after;
            // แทนที่ในข้อความตาม offset และ length
//            $text = substr_replace($text, $emojiCode, $emoji['offset'], $emoji['length']);
        }

//        dd($text);
        return $text;
    }
}
