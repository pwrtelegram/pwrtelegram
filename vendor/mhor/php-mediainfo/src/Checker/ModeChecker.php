<?php

namespace Mhor\MediaInfo\Checker;

use Mhor\MediaInfo\Attribute\Mode;

class ModeChecker extends AbstractAttributeChecker
{
    /**
     * @param array|string $rateMode
     *
     * @return Mode
     */
    public function create($rateMode)
    {
        $rateMode = (array) $rateMode;

        if (!isset($rateMode[1])) {
            $rateMode[1] = $rateMode[0];
        }

        return new Mode($rateMode[0], $rateMode[1]);
    }

    /**
     * @return array
     */
    public function getMembersFields()
    {
        return array(
            'overall_bit_rate_mode',
            'overall_bit_rate',
            'bit_rate_mode',
            'compression_mode',
            'codec',
            'format',
            'kind_of_stream',
            'writing_library',
            'id',
            'format_settings_sbr',
            'channel_positions',
            'default',
            'forced',
            'delay_origin',
            'scan_type',
            'interlacement',
            'scan_type',
            'frame_rate_mode',
            'format_settings_cabac',
            'unique_id',
        );
    }
}
