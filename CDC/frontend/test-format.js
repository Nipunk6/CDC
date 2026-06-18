const { AsYouType, getCountryCallingCode } = require('libphonenumber-js');

const value = '+919876543210';
const currentCountry = 'IN';
const callingCode = getCountryCallingCode(currentCountry);
let rawNational = value;
if (value.startsWith(`+${callingCode}`)) {
  rawNational = value.slice(callingCode.length + 1);
}
const cleanNational = rawNational.replace(/\D/g, "");
const f = new AsYouType(currentCountry);
console.log('Formatted:', f.input(cleanNational));

const val2 = '+12025550123';
const c2 = 'US';
const code2 = getCountryCallingCode(c2);
let raw2 = val2;
if (val2.startsWith(`+${code2}`)) raw2 = val2.slice(code2.length + 1);
const clean2 = raw2.replace(/\D/g, "");
console.log('Formatted US:', new AsYouType(c2).input(clean2));
