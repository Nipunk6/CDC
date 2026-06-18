const { parsePhoneNumberFromString } = require('libphonenumber-js');

console.log(parsePhoneNumberFromString('+1 98765')?.country);
console.log(parsePhoneNumberFromString('+44')?.country);
console.log(parsePhoneNumberFromString('+91')?.country);
console.log(parsePhoneNumberFromString('+91 98765')?.country);
console.log(parsePhoneNumberFromString('+1')?.country);
