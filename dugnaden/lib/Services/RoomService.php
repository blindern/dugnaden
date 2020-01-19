<?php

namespace Blindern\Dugnaden\Services;

use Blindern\Dugnaden\Dugnaden;
use Blindern\Dugnaden\Model\Room;

/**
 * The room service will cache all rooms to speed up lookup and
 * to simplify logic.
 */
class RoomService
{
    /** @var Dugnaden */
    private $dugnaden;

    /** @var Room[] */
    private $_rooms = null;

    function __construct(Dugnaden $dugnaden)
    {
        $this->dugnaden = $dugnaden;
    }

    /** @return Room */
    public function getById($id)
    {
        $rooms = $this->getAll();
        if (isset($rooms[$id])) {
            return $rooms[$id];
        }
        return null;
    }

    /**
     * Get all.
     *
     * @return Room[]
     */
    public function getAll()
    {
        if (!$this->_rooms) {
            $sql =
                "SELECT *
                FROM bs_rom
                ORDER BY rom_nr, rom_type";

            $stmt = $this->dugnaden->pdo->query($sql);

            $result = [];
            foreach ($stmt as $row) {
                $room = Room::fromRow($row);
                $result[$room->id] = $room;
            }

            $this->_rooms = $result;
        }

        return $this->_rooms;
    }
}
