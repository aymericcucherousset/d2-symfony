<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

/**
 * Official d2 themes (`d2 themes`), usable for the `theme` and `dark_theme` options.
 */
enum Theme: int
{
    // Light themes
    case NeutralDefault = 0;
    case NeutralGrey = 1;
    case FlagshipTerrastruct = 3;
    case CoolClassics = 4;
    case MixedBerryBlue = 5;
    case GrapeSoda = 6;
    case Aubergine = 7;
    case ColorblindClear = 8;
    case VanillaNitroCola = 100;
    case OrangeCreamsicle = 101;
    case ShirleyTemple = 102;
    case EarthTones = 103;
    case EvergladeGreen = 104;
    case ButteredToast = 105;
    case Terminal = 300;
    case TerminalGrayscale = 301;
    case Origami = 302;
    case C4 = 303;

    // Dark themes
    case DarkMauve = 200;
    case DarkFlagshipTerrastruct = 201;

    public function isDark(): bool
    {
        return self::DarkMauve === $this || self::DarkFlagshipTerrastruct === $this;
    }
}
