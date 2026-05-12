<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Afghanistan', 'iso2' => 'AF', 'iso3' => 'AFG', 'phone_code' => '+93'],
            ['name' => 'Albania', 'iso2' => 'AL', 'iso3' => 'ALB', 'phone_code' => '+355'],
            ['name' => 'Algeria', 'iso2' => 'DZ', 'iso3' => 'DZA', 'phone_code' => '+213'],
            ['name' => 'Argentina', 'iso2' => 'AR', 'iso3' => 'ARG', 'phone_code' => '+54'],
            ['name' => 'Australia', 'iso2' => 'AU', 'iso3' => 'AUS', 'phone_code' => '+61'],
            ['name' => 'Austria', 'iso2' => 'AT', 'iso3' => 'AUT', 'phone_code' => '+43'],
            ['name' => 'Bangladesh', 'iso2' => 'BD', 'iso3' => 'BGD', 'phone_code' => '+880'],
            ['name' => 'Belgium', 'iso2' => 'BE', 'iso3' => 'BEL', 'phone_code' => '+32'],
            ['name' => 'Brazil', 'iso2' => 'BR', 'iso3' => 'BRA', 'phone_code' => '+55'],
            ['name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN', 'phone_code' => '+1'],
            ['name' => 'Chile', 'iso2' => 'CL', 'iso3' => 'CHL', 'phone_code' => '+56'],
            ['name' => 'China', 'iso2' => 'CN', 'iso3' => 'CHN', 'phone_code' => '+86'],
            ['name' => 'Colombia', 'iso2' => 'CO', 'iso3' => 'COL', 'phone_code' => '+57'],
            ['name' => 'Croatia', 'iso2' => 'HR', 'iso3' => 'HRV', 'phone_code' => '+385'],
            ['name' => 'Czech Republic', 'iso2' => 'CZ', 'iso3' => 'CZE', 'phone_code' => '+420'],
            ['name' => 'Denmark', 'iso2' => 'DK', 'iso3' => 'DNK', 'phone_code' => '+45'],
            ['name' => 'Egypt', 'iso2' => 'EG', 'iso3' => 'EGY', 'phone_code' => '+20'],
            ['name' => 'Ethiopia', 'iso2' => 'ET', 'iso3' => 'ETH', 'phone_code' => '+251'],
            ['name' => 'Finland', 'iso2' => 'FI', 'iso3' => 'FIN', 'phone_code' => '+358'],
            ['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'phone_code' => '+33'],
            ['name' => 'Germany', 'iso2' => 'DE', 'iso3' => 'DEU', 'phone_code' => '+49'],
            ['name' => 'Ghana', 'iso2' => 'GH', 'iso3' => 'GHA', 'phone_code' => '+233'],
            ['name' => 'Greece', 'iso2' => 'GR', 'iso3' => 'GRC', 'phone_code' => '+30'],
            ['name' => 'Hungary', 'iso2' => 'HU', 'iso3' => 'HUN', 'phone_code' => '+36'],
            ['name' => 'India', 'iso2' => 'IN', 'iso3' => 'IND', 'phone_code' => '+91'],
            ['name' => 'Indonesia', 'iso2' => 'ID', 'iso3' => 'IDN', 'phone_code' => '+62'],
            ['name' => 'Iran', 'iso2' => 'IR', 'iso3' => 'IRN', 'phone_code' => '+98'],
            ['name' => 'Iraq', 'iso2' => 'IQ', 'iso3' => 'IRQ', 'phone_code' => '+964'],
            ['name' => 'Ireland', 'iso2' => 'IE', 'iso3' => 'IRL', 'phone_code' => '+353'],
            ['name' => 'Israel', 'iso2' => 'IL', 'iso3' => 'ISR', 'phone_code' => '+972'],
            ['name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'phone_code' => '+39'],
            ['name' => 'Japan', 'iso2' => 'JP', 'iso3' => 'JPN', 'phone_code' => '+81'],
            ['name' => 'Jordan', 'iso2' => 'JO', 'iso3' => 'JOR', 'phone_code' => '+962'],
            ['name' => 'Kenya', 'iso2' => 'KE', 'iso3' => 'KEN', 'phone_code' => '+254'],
            ['name' => 'South Korea', 'iso2' => 'KR', 'iso3' => 'KOR', 'phone_code' => '+82'],
            ['name' => 'Lebanon', 'iso2' => 'LB', 'iso3' => 'LBN', 'phone_code' => '+961'],
            ['name' => 'Malaysia', 'iso2' => 'MY', 'iso3' => 'MYS', 'phone_code' => '+60'],
            ['name' => 'Mexico', 'iso2' => 'MX', 'iso3' => 'MEX', 'phone_code' => '+52'],
            ['name' => 'Morocco', 'iso2' => 'MA', 'iso3' => 'MAR', 'phone_code' => '+212'],
            ['name' => 'Netherlands', 'iso2' => 'NL', 'iso3' => 'NLD', 'phone_code' => '+31'],
            ['name' => 'New Zealand', 'iso2' => 'NZ', 'iso3' => 'NZL', 'phone_code' => '+64'],
            ['name' => 'Nigeria', 'iso2' => 'NG', 'iso3' => 'NGA', 'phone_code' => '+234'],
            ['name' => 'Norway', 'iso2' => 'NO', 'iso3' => 'NOR', 'phone_code' => '+47'],
            ['name' => 'Pakistan', 'iso2' => 'PK', 'iso3' => 'PAK', 'phone_code' => '+92'],
            ['name' => 'Philippines', 'iso2' => 'PH', 'iso3' => 'PHL', 'phone_code' => '+63'],
            ['name' => 'Poland', 'iso2' => 'PL', 'iso3' => 'POL', 'phone_code' => '+48'],
            ['name' => 'Portugal', 'iso2' => 'PT', 'iso3' => 'PRT', 'phone_code' => '+351'],
            ['name' => 'Romania', 'iso2' => 'RO', 'iso3' => 'ROU', 'phone_code' => '+40'],
            ['name' => 'Russia', 'iso2' => 'RU', 'iso3' => 'RUS', 'phone_code' => '+7'],
            ['name' => 'Saudi Arabia', 'iso2' => 'SA', 'iso3' => 'SAU', 'phone_code' => '+966'],
            ['name' => 'Singapore', 'iso2' => 'SG', 'iso3' => 'SGP', 'phone_code' => '+65'],
            ['name' => 'South Africa', 'iso2' => 'ZA', 'iso3' => 'ZAF', 'phone_code' => '+27'],
            ['name' => 'Spain', 'iso2' => 'ES', 'iso3' => 'ESP', 'phone_code' => '+34'],
            ['name' => 'Sri Lanka', 'iso2' => 'LK', 'iso3' => 'LKA', 'phone_code' => '+94'],
            ['name' => 'Sweden', 'iso2' => 'SE', 'iso3' => 'SWE', 'phone_code' => '+46'],
            ['name' => 'Switzerland', 'iso2' => 'CH', 'iso3' => 'CHE', 'phone_code' => '+41'],
            ['name' => 'Tanzania', 'iso2' => 'TZ', 'iso3' => 'TZA', 'phone_code' => '+255'],
            ['name' => 'Thailand', 'iso2' => 'TH', 'iso3' => 'THA', 'phone_code' => '+66'],
            ['name' => 'Tunisia', 'iso2' => 'TN', 'iso3' => 'TUN', 'phone_code' => '+216'],
            ['name' => 'Turkey', 'iso2' => 'TR', 'iso3' => 'TUR', 'phone_code' => '+90'],
            ['name' => 'Uganda', 'iso2' => 'UG', 'iso3' => 'UGA', 'phone_code' => '+256'],
            ['name' => 'Ukraine', 'iso2' => 'UA', 'iso3' => 'UKR', 'phone_code' => '+380'],
            ['name' => 'United Arab Emirates', 'iso2' => 'AE', 'iso3' => 'ARE', 'phone_code' => '+971'],
            ['name' => 'United Kingdom', 'iso2' => 'GB', 'iso3' => 'GBR', 'phone_code' => '+44'],
            ['name' => 'United States', 'iso2' => 'US', 'iso3' => 'USA', 'phone_code' => '+1'],
            ['name' => 'Vietnam', 'iso2' => 'VN', 'iso3' => 'VNM', 'phone_code' => '+84'],
            ['name' => 'Zimbabwe', 'iso2' => 'ZW', 'iso3' => 'ZWE', 'phone_code' => '+263'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(['iso2' => $country['iso2']], array_merge($country, ['is_active' => true]));
        }
    }
}
