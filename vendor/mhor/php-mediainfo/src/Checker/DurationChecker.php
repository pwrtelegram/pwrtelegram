<?php

namespace Mhor\MediaInfo\Checker;

use Mhor\MediaInfo\Attribute\Duration;

class DurationChecker extends AbstractAttributeChecker
{
    /**
     * @param array $durations
     *
     * @return Duration
     */
    public function create($durations)
    {
        $duration = new Duration($durations[0]);

        return $duration;
    }

    /**
     * @return array
     */
    public function getMembersFields()
    {
        return array(
            'duration',
            'delay_relative_to_video',
            'video0_delay',
            'delay',
        );
    }
}
