const { validatePhoneNumberLength } = require('libphonenumber-js/max');

console.log('IN +9198765432101', validatePhoneNumberLength('+9198765432101', 'IN'));
console.log('IN 98765432101', validatePhoneNumberLength('98765432101', 'IN'));
