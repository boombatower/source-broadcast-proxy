<?php

use Docker\Container;
use Docker\Docker;

class SourceBroadcastProxyTest extends \PHPUnit_Framework_TestCase
{
  public function testProxyDocker()
  {
    $this->_testProxy(true);
  }

  public function testProxyPlain()
  {
    $this->_testProxy(false);
  }

  protected function _testProxy($docker = true)
  {
    // Not enough disk space to build/download containers so pointless.
    if (getenv('TRAVIS')) return;

    $manager = (new Docker())->getContainerManager();

    $fake = new Container(['Image' => 'boombatower/source-server-fake']);
    $manager->create($fake);
    $manager->start($fake, ['PublishAllPorts' => true]);

    $port = $fake->getMappedPort(27015, 'udp')->getHostPort();
    $this->assertInternalType('integer', $port);


    if ($docker) {
      $proxy = new Container(['Image' => 'boombatower/source-broadcast-proxy']);
      $manager->create($proxy);
      $manager->start($proxy, ['NetworkMode' => 'host']);
    }
    else {
      $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['file', 'proxy.log', 'w'],
        2 => ['file', '/dev/null', 'a'],
      ];
      $this->process = proc_open('php proxy.php', $descriptorspec, $pipes);
    }
    sleep(1); // Ensure it has time to start.

    require_once 'vendor/koraktor/steam-condenser/lib/steam-condenser.php'; // required
    $socket = new UDPSocket();
    $socket->connect('0.0.0.0', 27015, 1000);
    $socket->send(hex2bin('FFFFFFFF54536F7572636520456E67696E6520517565727900'));
    $socket->select(5000);
    $response = $socket->recv(1400);

    require_once 'util.php';
    $buffer = ByteBuffer::wrap(hex2bin('ffffffff49115465616d20466f72747265737300706c5f6261647761746572007466005465616d20466f72747265737300b801001800646c00013234323030383000b101c0023ca209541240017061796c6f616400b801000000000000'));
    $this->assertTrue(set_port($buffer, $port));
    $this->assertEquals($response, $buffer->_array());

    // Send second time to see that logs indicate response is cached.
    $socket->send(hex2bin('FFFFFFFF54536F7572636520456E67696E6520517565727900'));
    $socket->select(5000);
    $this->assertEquals($socket->recv(1400), $response);

    // Verify logs looks correct and indicate cache hit.
    // Seems to be extraneous binary garbage in response that Docker code parses. As such workaround
    // by adding .* in between lines where garbage seems to manifest.
    $expected = '@Received A2S_INFO request from (\d+\.\d+\.\d+\.\d+:\d+)
.*-> Forwarding to Source server on port (\d+)
.*Handled in [\d.]+ seconds
.*Received A2S_INFO request from \1
.*-> Sending 1 cached responses
.*Handled in [\d.]+ seconds@s';
    if ($docker) {
      $logs = $manager->attach($proxy, true, false)->getBody()->__toString();
    }
    else {
      $logs = file_get_contents('proxy.log');
    }
    $this->assertTrue((bool) preg_match($expected, $logs, $match));
    $this->assertEquals($match[2], $port);
  }

  public function testSetPort()
  {
    $initial  = 'ffffffff49115465616d20466f72747265737300706c5f6261647761746572007466005465616d20466f72747265737300b801001800646c00013234323030383000b101c0023ca209541240017061796c6f616400b801000000000000';
    $response = 'ffffffff49115465616d20466f72747265737300706c5f6261647761746572007466005465616d20466f72747265737300b801001800646c00013234323030383000b13a34023ca209541240017061796c6f616400b801000000000000';

    require_once 'vendor/koraktor/steam-condenser/lib/steam-condenser.php'; // required
    require_once 'util.php';
    $buffer = ByteBuffer::wrap(hex2bin($initial));
    $this->assertTrue(set_port($buffer, 13370));
    $this->assertEquals(hex2bin($response), $buffer->_array());
  }

  protected function tearDown()
  {
    $images = [
      'boombatower/source-broadcast-proxy:latest',
      'boombatower/source-server-fake:latest'
    ];
    $manager = (new Docker())->getContainerManager();
    foreach ($manager->findAll() as $container) {
      if (in_array($container->getConfig()['Image'], $images)) {
        @$manager->stop($container, 1);
        @$manager->remove($container);
      }
    }

    if (isset($this->process) && is_resource($this->process)) {
      proc_terminate($this->process);
      unlink('proxy.log');
    }
  }
}
