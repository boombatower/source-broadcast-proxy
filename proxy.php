<?php

/**
 * @file
 * Forwards Valve's Source Engine LAN broadcast packets to non-default ports.
 *
 * Designed to look for Docker containers that expose default source engine port. See the README for
 * more information on configuration and usage.
 *
 * @see https://developer.valvesoftware.com/wiki/Server_queries
 */

if (PHP_SAPI != 'cli') exit();
require_once 'vendor/autoload.php';
require_once 'vendor/koraktor/steam-condenser/lib/steam-condenser.php'; // required

const PACKET_MAX = 16384; // 2^14.
const ELAPSE_MAX = 2.8; // Seconds that a Source client waits for response (3) with margin.
const CACHE_LIFETIME = 5; // Number of seconds to cache results.

$valve_ports = range(27015, 27020);

// Bind a socket to the first default Source port. Be sure to listen on address 0.0.0.0 so that
// broadcast packets are picked up under Linux.
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, '0.0.0.0', 27015);

// If running as Docker container then attempt to connect via TCP (which only works if running with
// --net=host, alternatives are overly complex especially with forwarding). Otherwise, if running
// normally look for the default unix socket unix:///var/run/docker.sock.
$client = file_exists('/.dockerinit') ?
  new Docker\Http\DockerClient([], 'tcp://0.0.0.0:' . (getenv('DOCKER_PORT') ?: 2375)) : null;
$manager = (new Docker\Docker($client))->getContainerManager();

// Determine Docker container ID (if running as one), otherwise will result in blank.
$id_self = trim(`cat /proc/self/cgroup | grep -o  -e "docker-.*.scope" | head -n 1 | sed "s/docker-\(.*\).scope/\\1/"`);

// Initialize cache variables.
$responses = [];
$responses_expire = 0;
do {
  // Block until data received.
  $from = '';
  $from_port = 0;
  socket_recvfrom($socket, $buffer, PACKET_MAX, 0, $from, $from_port);
  $time_start = microtime(true);

  echo "Received A2S_INFO request from $from:$from_port" . PHP_EOL;

  // Only bother to forward if responses are expired.
  $time_current = time();
  if ($time_current >= $responses_expire) {
    // Forward packet to all running Docker containers that expose a Source Engine port.
    $responses = [];
    $responses_expire = $time_current + CACHE_LIFETIME;
    $sockets = [];
    foreach ($manager->findAll() as $container) {
      // Do not forward request to self (good 'ol infinite loops).
      if ($container->getId() == $id_self) continue;

      foreach ($container->getData()['Ports'] as $port) {
        if (in_array($port['PrivatePort'], $valve_ports)) {
          echo "-> Forwarding to Source server on port {$port['PublicPort']}" . PHP_EOL;

          // Forward the packet to the Source server.
          $sockets[$port['PublicPort']] = new UDPSocket();
          $sockets[$port['PublicPort']]->connect('0.0.0.0', $port['PublicPort'], 1000);
          $sockets[$port['PublicPort']]->send($buffer);
        }
      }
    }

    // Check for responses and send them back to broadcast client. Forwarding the broadcast packet
    // without waiting ensures that each server has the maximum amount of time to response.
    foreach ($sockets as $port => $forward) {
      $time_left = ELAPSE_MAX - (microtime(true) - $time_start);
      if ($forward->select((int) $time_left * 1000)) {
        $data = $forward->recv(PACKET_MAX);
        $forward->close();

        // Change the server port to match the public port for the container.
        $buffer = ByteBuffer::wrap($data);
        if (!set_port($buffer, (int) $port)) {
          echo "-> Failed to set port in response from server on port $port" . PHP_EOL;
        }

        // Cache respponse to ensure that very high request rates still see timely responses.
        $responses[] = $response = $buffer->_array();

        // Send server response back to broadcast client.
        socket_sendto($socket, $response, strlen($response), 0, $from, $from_port);
      }
      else {
        echo "-> No response from server on port $port" . PHP_EOL;
      }
    }
  }
  // Use cached respones.
  else {
    echo '-> Sending ' . number_format(count($responses)) . ' cached responses' . PHP_EOL;
    foreach ($responses as $response) {
      socket_sendto($socket, $response, strlen($response), 0, $from, $from_port);
    }
  }
  echo 'Handled in ' . number_format(microtime(true) - $time_start, 6) . ' seconds' . PHP_EOL;
}
// Used for testing: pass any extra argument to cause loop to only execute once.
while ($argc == 1);

socket_close($socket);


/**
 * Override the server port contained in a S2A_INFO2_Packet buffer.
 *
 * @param ByteBuffer $buffer
 *   Buffer containing S2A_INFO2_Packet packet.
 * @param int $port
 *   Value to set for the server port.
 */
function set_port(ByteBuffer $buffer, $port)
{
  $buffer->rewind();

  $buffer->getByte();
  $buffer->getString();
  $buffer->getString();
  $buffer->getString();
  $buffer->getString();
  $buffer->getShort();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getByte();
  $buffer->getString();

  if($buffer->remaining() > 0) {
    $extra_data_flag = $buffer->getByte();

    if ($extra_data_flag & S2A_INFO2_Packet::EDF_GAME_PORT) {
      $buffer->put(pack('S', $port));
      return true;
    }
  }
  return false;
}
