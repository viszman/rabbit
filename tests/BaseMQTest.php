<?php

namespace Viszman\Tests;


use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PHPUnit\Framework\TestCase;

class BaseMQTest extends TestCase
{

    public function testClose()
    {
        $stub = $this->getMockForAbstractClass('Viszman\BaseMQ');
        $this->assertTrue($stub->close());
    }

    public function testSetupWorker()
    {
        $stub = $this->getMockForAbstractClass('Viszman\BaseMQ');

        $this->assertNull($stub->setupWorker(static function () {
            return true;
        }));
    }

    public function testCreateTask()
    {
        $stub = $this->getMockForAbstractClass('Viszman\BaseMQ');
        $stub->createTask('test');
        $this->assertTrue(true);
    }

    protected function prepareAMQPConnection()
    {
        return $this->getMockBuilder(AMQPConnection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function prepareAMQPChannel()
    {
        return $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
