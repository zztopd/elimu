<?php

namespace Tests\Unit;

use App\Support\Position;
use Tests\TestCase;

class PositionTest extends TestCase
{
    protected const ROWS = 12;
    protected const COLUMNS = 8;

    public function provider()
    {
        return [
            [1, 'A01', false, false],
            [2, 'B01', false, false],
            [8, 'H01', false, false],
            [12, 'D02', false, false],
            [72, 'H09', false, false],
            [96, 'H12', false, false],

            [0, 'A01', true, false],
            [7, 'H01', true, false],
            [8, 'A02', true, false],
            [63, 'H08', true, false],
            [95, 'H12', true, false],
            [67, 'D09', true, false],

            [12, 'P001 D02', false, true],
            [168, 'P002 H09', false, true],
            [288, 'P003 H12', false, true],

            [96, 'P002 A01', true, true],
            [191, 'P002 H12', true, true],
            [259, 'P003 D09', true, true],
        ];
    }

    /**
     * @test
     * @dataProvider provider
     */
    public function it_determines_the_correct_label($position, $label, $startWithZero, $showPlates)
    {
        $this->assertEquals(
            $label,
            Position::fromPosition($position)
                ->startWithZero($startWithZero)
                ->withColumns(self::COLUMNS)
                ->withRows(self::ROWS)
                ->showPlates($showPlates)
                ->toLabel()
        );
    }

    /**
     * @test
     * @dataProvider provider
     */
    public function it_determines_the_correct_position($position, $label, $startWithZero, $showPlates)
    {
        $this->assertEquals(
            $position,
            Position::fromLabel($label)
                ->startWithZero($startWithZero)
                ->withColumns(self::COLUMNS)
                ->withRows(self::ROWS)
                ->showPlates($showPlates)
                ->toPosition()
        );
    }

    public function formatProvider()
    {
        return [
            [1, 'A01', 'ABC', '123'],
            [1, 'A01', '123', 'ABC'],
            [2, 'B01', 'ABC', '123'],
            [2, 'A02', '123', 'ABC'],
            [1, 'a01', 'abc', '123'],
            [8, 'a08', '123', 'abc'],
            [9, 'b01', '123', 'abc'],
        ];
    }

    /**
     * @test
     * @dataProvider formatProvider
     * @param $label
     * @param $position
     * @param $columnFormat
     * @param $rowFormat
     */
    public function it_determines_the_correct_label_format($position, $label, $columnFormat, $rowFormat)
    {
        $this->assertEquals(
            $label,
            Position::fromPosition($position)
                ->withColumns(self::COLUMNS)
                ->withColumnFormat($columnFormat)
                ->withRows(self::ROWS)
                ->withRowFormat($rowFormat)
                ->toLabel()
        );
    }

    /**
     * @test
     * @dataProvider formatProvider
     * @param $label
     * @param $position
     * @param $columnFormat
     * @param $rowFormat
     */
    public function it_determines_the_correct_position_from_label_format($position, $label, $columnFormat, $rowFormat)
    {
        $this->assertEquals(
            $position,
            Position::fromLabel($label)
                ->withColumns(self::COLUMNS)
                ->withColumnFormat($columnFormat)
                ->withRows(self::ROWS)
                ->withRowFormat($rowFormat)
                ->toPosition()
        );
    }
}
