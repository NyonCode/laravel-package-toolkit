<?php

namespace NyonCode\LaravelPackageToolkit\Support\Enums;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

enum Language: string
{
    case AB = 'Abkhazian';
    case AA = 'Afar';
    case AF = 'Afrikaans';
    case AK = 'Akan';
    case SQ = 'Albanian';
    case AM = 'Amharic';
    case AR = 'Arabic';
    case AN = 'Aragonese';
    case HY = 'Armenian';
    case AS = 'Assamese';
    case AV = 'Avaric';
    case AE = 'Avestan';
    case AY = 'Aymara';
    case AZ = 'Azerbaijani';
    case BM = 'Bambara';
    case BA = 'Bashkir';
    case EU = 'Basque';
    case BE = 'Belarusian';
    case BN = 'Bengali';
    case BH = 'Bihari languages';
    case BI = 'Bislama';
    case BS = 'Bosnian';
    case BR = 'Breton';
    case BG = 'Bulgarian';
    case MY = 'Burmese';
    case CA = 'Catalan';
    case CS = 'Czech';
    case CH = 'Chamorro';
    case CE = 'Chechen';
    case ZH = 'Chinese';
    case CV = 'Chuvash';
    case KW = 'Cornish';
    case CO = 'Corsican';
    case CR = 'Cree';
    case HR = 'Croatian';
    case DA = 'Danish';
    case NL = 'Dutch';
    case DZ = 'Dzongkha';
    case EN = 'English';
    case EO = 'Esperanto';
    case ET = 'Estonian';
    case EE = 'Ewe';
    case FO = 'Faroese';
    case FJ = 'Fijian';
    case FI = 'Finnish';
    case FR = 'French';
    case FF = 'Fulah';
    case GL = 'Galician';
    case KA = 'Georgian';
    case DE = 'German';
    case EL = 'Greek';
    case GN = 'Guarani';
    case GU = 'Gujarati';
    case HT = 'Haitian';
    case HA = 'Hausa';
    case HE = 'Hebrew';
    case HZ = 'Herero';
    case HI = 'Hindi';
    case HO = 'Hiri Motu';
    case HU = 'Hungarian';
    case IS = 'Icelandic';
    case IO = 'Ido';
    case IG = 'Igbo';
    case ID = 'Indonesian';
    case IE = 'Interlingue';
    case IU = 'Inuktitut';
    case IK = 'Inupiaq';
    case GA = 'Irish';
    case IT = 'Italian';
    case JA = 'Japanese';
    case JV = 'Javanese';
    case KL = 'Kalaallisut';
    case KN = 'Kannada';
    case KR = 'Kanuri';
    case KS = 'Kashmiri';
    case KK = 'Kazakh';
    case KM = 'Central Khmer';
    case KI = 'Kikuyu';
    case RW = 'Kinyarwanda';
    case KY = 'Kirghiz';
    case KV = 'Komi';
    case KG = 'Kongo';
    case KO = 'Korean';
    case KJ = 'Kuanyama';
    case KU = 'Kurdish';
    case LO = 'Lao';
    case LA = 'Latin';
    case LV = 'Latvian';
    case LI = 'Limburgan';
    case LN = 'Lingala';
    case LT = 'Lithuanian';
    case LB = 'Luxembourgish';
    case MK = 'Macedonian';
    case MG = 'Malagasy';
    case MS = 'Malay';
    case ML = 'Malayalam';
    case MT = 'Maltese';
    case GV = 'Manx';
    case MI = 'Maori';
    case MR = 'Marathi';
    case MH = 'Marshallese';
    case MN = 'Mongolian';
    case NA = 'Nauru';
    case NV = 'Navajo';
    case ND = 'North Ndebele';
    case NR = 'South Ndebele';
    case NG = 'Ndonga';
    case NE = 'Nepali';
    case NO = 'Norwegian';
    case NB = 'Norwegian Bokmål';
    case NN = 'Norwegian Nynorsk';
    case NY = 'Nyanja';
    case OC = 'Occitan';
    case OJ = 'Ojibwa';
    case OR = 'Oriya';
    case OM = 'Oromo';
    case OS = 'Ossetian';
    case PI = 'Pali';
    case PS = 'Pashto';
    case FA = 'Persian';
    case PL = 'Polish';
    case PT = 'Portuguese';
    case PA = 'Punjabi';
    case QU = 'Quechua';
    case RO = 'Romanian';
    case RM = 'Romansh';
    case RN = 'Rundi';
    case RU = 'Russian';
    case SM = 'Samoan';
    case SG = 'Sango';
    case SA = 'Sanskrit';
    case SC = 'Sardinian';
    case SR = 'Serbian';
    case SN = 'Shona';
    case II = 'Sichuan Yi';
    case SD = 'Sindhi';
    case SI = 'Sinhala';
    case SK = 'Slovak';
    case SL = 'Slovenian';
    case SO = 'Somali';
    case ST = 'Southern Sotho';
    case ES = 'Spanish';
    case SU = 'Sundanese';
    case SW = 'Swahili';
    case SS = 'Swati';
    case SV = 'Swedish';
    case TL = 'Tagalog';
    case TY = 'Tahitian';
    case TG = 'Tajik';
    case TA = 'Tamil';
    case TT = 'Tatar';
    case TE = 'Telugu';
    case TH = 'Thai';
    case BO = 'Tibetan';
    case TI = 'Tigrinya';
    case TO = 'Tonga';
    case TS = 'Tsonga';
    case TN = 'Tswana';
    case TR = 'Turkish';
    case TK = 'Turkmen';
    case TW = 'Twi';
    case UG = 'Uighur';
    case UK = 'Ukrainian';
    case UR = 'Urdu';
    case UZ = 'Uzbek';
    case VE = 'Venda';
    case VI = 'Vietnamese';
    case VO = 'Volapük';
    case WA = 'Walloon';
    case CY = 'Welsh';
    case FY = 'Western Frisian';
    case WO = 'Wolof';
    case XH = 'Xhosa';
    case YI = 'Yiddish';
    case YO = 'Yoruba';
    case ZA = 'Zhuang';
    case ZU = 'Zulu';

    /**
     * Returns a collection of all language names
     *
     * @return Collection<int, string>
     */
    public static function names(): Collection
    {
        return collect(self::cases())->map(fn($language) => $language->value);
    }

    /**
     * Returns a collection of all language codes
     *
     * @return Collection<int, string>
     */
    public static function codes(): Collection
    {
        return collect(self::cases())->map(fn($language) => Str::lower($language->name));
    }

    /**
     * Returns a collection of all language names and codes
     *
     * @return Collection<int, Language>
     */
    public static function collection(): Collection
    {
        return collect(self::cases());
    }
}