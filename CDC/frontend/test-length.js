const { validatePhoneNumberLength } = require('libphonenumber-js');

console.log('US +12025550123', validatePhoneNumberLength('+12025550123', 'US'));
console.log('US +120255501234', validatePhoneNumberLength('+120255501234', 'US'));
console.log('IN +919876543210', validatePhoneNumberLength('+919876543210', 'IN'));
console.log('IN +9198765432101', validatePhoneNumberLength('+9198765432101', 'IN'));
console.log('IN 9876543210', validatePhoneNumberLength('9876543210', 'IN'));
console.log('IN 987', validatePhoneNumberLength('987', 'IN'));
