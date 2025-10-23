<?php

namespace Gametech\Admin\Transformers;

use Gametech\Core\Contracts\FailedRequest;
use Gametech\Core\Models\FailedRequestProxy;
use League\Fractal\TransformerAbstract;

class FailedRequestTransformer extends TransformerAbstract
{
    public function transform(FailedRequest $model)
    {

        return [
            'id' => (int) $model->id,
            'company' => $model->company,
            'game_user' => $model->game_user,
            'url' => $model->url,
            'method' => $model->method,
            'status' => $model->status,
            'duration' => $model->duration,
            'created_at' => $model->created_at,
        ];
    }
}
