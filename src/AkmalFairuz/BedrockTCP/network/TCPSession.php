<?php

declare(strict_types=1);

namespace AkmalFairuz\BedrockTCP\network;

use AkmalFairuz\BedrockTCP\compressor\TCPCompressor;
use AkmalFairuz\Sobana\server\ServerSession;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use pocketmine\utils\Binary;
use pocketmine\utils\Utils;
use function strlen;
use function substr;

/**
 * @property TCPServerManager $serverManager
 */

class TCPSession extends ServerSession{

    private NetworkSession $networkSession;

    private string $pendingBuffer = "";

    public function onConnect(): void{
        $this->networkSession = new TCPNetworkSession(
            $this->serverManager->getServer(),
            $this->serverManager->getNetwork()->getSessionManager(),
            PacketPool::getInstance(),
            new TCPPacketSender($this),
            RakLibInterface::getBroadcaster(Server::getInstance(), ProtocolInfo::CURRENT_PROTOCOL),
            TCPCompressor::getInstance(),
            $this->getIp(),
            $this->getPort()
        );
        $this->networkSession->updatePing(1);
    }

    public function handlePacket(string $packet): void{
        $this->pendingBuffer .= $packet;

        while('' !== $this->pendingBuffer){
            $frameLength = Binary::readInt(substr($this->pendingBuffer, 0, 4));
            $payload = substr($this->pendingBuffer, 4, $frameLength);
            if($frameLength !== strlen($payload)){
                break;
            }
            $this->pendingBuffer = substr($this->pendingBuffer, 4 + $frameLength);
            try{
                $this->networkSession->handleEncoded($payload);
            }catch(PacketHandlingException $e){
                $errorId = bin2hex(random_bytes(6));

                $logger = $this->networkSession->getLogger();
                $logger->error("Bad packet (error ID $errorId): " . $e->getMessage());

                //intentionally doesn't use logException, we don't want spammy packet error traces to appear in release mode
                $logger->debug(implode("\n", Utils::printableExceptionInfo($e)));
                $this->networkSession->disconnect("Packet processing error (Error ID: $errorId)");
            }
        }
    }

    public function onClose(): void{
        $this->networkSession->onClientDisconnect("unknown"); // TODO: reason
    }
}
