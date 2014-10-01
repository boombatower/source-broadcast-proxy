<?php

/**
 * @file
 * Util functions.
 *
 * Sure this could be organized fancily, autoloaded, maybe even Symfony console application, but
 * that all seems like overkill for such a small application.
 */

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
