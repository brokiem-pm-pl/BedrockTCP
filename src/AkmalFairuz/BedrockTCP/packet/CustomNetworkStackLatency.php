<?php

declare(strict_types=1);

namespace AkmalFairuz\BedrockTCP\packet;

use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

class CustomNetworkStackLatency extends NetworkStackLatencyPacket{

    public function canBeSentBeforeLogin(): bool{
        return true;
    }
}