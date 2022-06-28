<?php

declare(strict_types=1);

namespace Tests\OneBot\V12\Action;

use OneBot\V12\Action\ActionResponse;
use OneBot\V12\RetCode;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ActionResponseTest extends TestCase
{
    public function testOk()
    {
        $response = new ActionResponse();
        $response->data['a'] = 'b';
        $response->echo = 'ppp';
        $this->assertEquals($response, ActionResponse::create('ppp')->ok(['a' => 'b']));
    }

    public function testGetIterator()
    {
        $response = new ActionResponse();
        $response->data['a'] = 'b';
        $response->echo = 'ppp';
        $this->assertEquals((array) $response, (array) ActionResponse::create('ppp')->ok(['a' => 'b'])->getIterator());
    }

    public function testJsonSerialize()
    {
        $response = new ActionResponse();
        $response->data['a'] = 'b';
        $response->echo = 'ppp';
        $this->assertEquals(json_encode($response), json_encode(ActionResponse::create('ppp')->ok(['a' => 'b'])));
    }

    public function testFail()
    {
        $response = new ActionResponse();
        $response->retcode = RetCode::UNSUPPORTED_ACTION;
        $response->status = 'failed';
        $response->message = RetCode::getMessage(RetCode::UNSUPPORTED_ACTION);
        $this->assertEquals($response, ActionResponse::create()->fail(RetCode::UNSUPPORTED_ACTION));
    }

    public function testCreate()
    {
        $response = new ActionResponse();
        $response->echo = 'ppp';
        $this->assertEquals($response, ActionResponse::create('ppp'));
    }
}
