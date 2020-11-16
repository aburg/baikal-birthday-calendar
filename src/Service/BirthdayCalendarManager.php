<?php

namespace Aburg\BaikalBirthdayCalendar\Service;

class BirthdayCalendarManager {
    /** @var PDO */
    private $pdo;

    /** @var \Sabre\CardDAV\Backend\PDO */
    private $cardBackend;

    /** @var \Sabre\CalDAV\Backend\PDO */
    private $calendarBackend;

    private $birthdayEventSummary;

    /**
     * MyServer constructor.
     *
     * @param string $birthdayEventSummary
     */
    public function __construct($birthdayEventSummary = '%FULLNAME% has a birthday') {
        $this->birthdayEventSummary = $birthdayEventSummary;

        if (isset($GLOBALS['DB'])) {
            $this->pdo = $GLOBALS['DB']->getPDO();
            $this->cardBackend = new \Sabre\CardDAV\Backend\PDO($this->pdo);
            $this->calendarBackend = new \Sabre\CalDAV\Backend\PDO($this->pdo);
        }
    }

    public function updateBirthdayCalendar($addressbookId, $calendarId) {
        $this->log('creating/updating birthday events...');

        // i dont get this, but it seems to work!?!
        $calendarId = [$calendarId, 'dummy'];

        $numCreated = 0;
        $numUpdated = 0;
        $numOk = 0;

        foreach ($this->cardBackend->getCards($addressbookId) as $cardData) {
            $vcard = $this->loadVcard($addressbookId, $cardData['uri']);

            $calUri = $this->calcBirthdayEventUri($vcard);
            $vcal = $this->loadVcal($calendarId, $calUri);
            if (!$vcal) {
                $this->createBirthdayEventForCard($calendarId, $vcard, $cardData['uri']);
                $this->log('birthday event created for ' . $vcard->FN->getValue());
                ++$numCreated;
            } else {
                if ($this->isBirthdayTheSame($vcard, $vcal)) {
                    $this->log('birthday was already ok for ' . $vcard->FN->getValue());
                    ++$numOk;
                } else {
                    $this->transferBirthday($vcard, $vcal);
                    $this->updateVcal($calendarId, $calUri, $vcal);
                    $this->log('birthday updated for ' . $vcard->FN->getValue());
                    ++$numUpdated;
                }
            }
        }

        $this->log("DONE! ($numCreated created, $numUpdated updated, $numOk untouched because they already were okay)");

        $this->removeOrphanedBirthdayEvents($addressbookId, $calendarId);
    }

    private function removeOrphanedBirthdayEvents($addressbookId, $calendarId) {
        $this->log('removing orphaned birthday events...');

        $numRemoved = 0;
        $numKept = 0;
        $numUnknown = 0;

        foreach ($this->calendarBackend->getCalendarObjects($calendarId) as $calendarObject) {
            $vcal = $this->loadVcal($calendarId, $calendarObject['uri']);
            if (!$vcal->VEVENT->NOTE) {
                ++$numUnknown;
                continue;
            }
            $cardUri = json_decode($vcal->VEVENT->NOTE->getValue(), true)['originating_card_uri'] ?? '';
            if (!$cardUri) {
                ++$numUnknown;
                continue;
            }
            if ($this->cardBackend->getCard($addressbookId, $cardUri)) {
                ++$numKept;
            } else {
                $this->calendarBackend->deleteCalendarObject($calendarId, $calendarObject['uri']);
                ++$numRemoved;
            }
        }

        $this->log("DONE! ($numRemoved removed, $numKept kept, $numUnknown unknown so we did not touch them)");
    }

    /**
     * @param $addressbookId
     * @param $cardUri
     *
     * @return \Sabre\VObject\Component\VCard
     */
    private function loadVcard($addressbookId, $cardUri) {
        $card = $this->cardBackend->getCard($addressbookId, $cardUri);

        return \Sabre\VObject\Reader::read($card['carddata']);
    }

    private function createBirthdayEventForCard($calendarId, $vcard, $cardUri) {
        $calendarObjectUri = $this->calcBirthdayEventUri($vcard);
        $vcal = new \Sabre\VObject\Component\VCalendar([
            'VEVENT' => [
                'SUMMARY' => $this->calcBirthdayEventSummary($vcard),
                'RRULE'   => 'FREQ=YEARLY',
                // we will use this for removing orphaned birthday events
                'NOTE'    => json_encode(['originating_card_uri' => $cardUri]),
                // these will get overridden in a sec
                'DTSTART' => 1,
                'DTEND'   => 1,
            ]
        ]);
        $this->transferBirthday($vcard, $vcal);
        $this->calendarBackend->createCalendarObject($calendarId, $calendarObjectUri, $vcal->serialize());
    }

    /**
     * @param \Sabre\VObject\Component\VCard $vcard
     * @param \Sabre\VObject\Component\VCalendar $vcal
     */
    private function transferBirthday($vcard, $vcal) {
        $bday = $vcard->BDAY->getValue();
        $vcal->VEVENT->DTSTART->setValue($bday);
        $vcal->VEVENT->DTEND->setValue((int) $bday + 1);
    }

    /**
     * @param \Sabre\VObject\Component\VCalendar $vcard
     *
     * @return string
     */
    private function calcBirthdayEventUri($vcard) {
        return 'bday_' . preg_replace('/\W+/', '', strtolower($vcard->FN->getValue())) . '.ics';
    }

    /**
     * @param \Sabre\VObject\Component\VCalendar $vcard
     *
     * @return string
     */
    private function calcBirthdayEventSummary($vcard) {
        return str_replace('%FULLNAME%', $vcard->FN->getValue(), $this->birthdayEventSummary);
    }

    /**
     * @param $calendarId
     * @param $calUri
     *
     * @return \Sabre\VObject\Component\VCalendar
     */
    private function loadVcal($calendarId, $calUri) {
        $cal = $this->calendarBackend->getCalendarObject($calendarId, $calUri);

        return $cal ? \Sabre\VObject\Reader::read($cal['calendardata']) : null;
    }

    /**
     * @param int $calendarId
     * @param string $calUri
     * @param \Sabre\VObject\Component\VCalendar $vcal
     */
    private function updateVcal($calendarId, $calUri, $vcal) {
        $this->calendarBackend->updateCalendarObject($calendarId, $calUri, $vcal->serialize());
    }

    private function isBirthdayTheSame($vcard, $vcal) {
        return $vcard->BDAY->getValue() === $vcal->VEVENT->DTSTART->getValue();
    }

    private function log($msg) {
        echo($msg . '</br>');
    }
}