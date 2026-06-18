const { parsePhoneNumberWithError, ParseError } = require('libphonenumber-js');

function test(number, defaultCountry) {
  try {
    const pn = parsePhoneNumberWithError(number, defaultCountry);
    console.log(`[${number}] Parsed successfully. Valid:`, pn.isValid());
  } catch (err) {
    if (err instanceof ParseError) {
      console.log(`[${number}] Error:`, err.message);
    } else {
      console.log(`[${number}] Unknown error:`, err);
    }
  }
}

test('+919876543210', 'IN');
test('+9198765432101', 'IN');
test('+12025550123', 'US');
test('+120255501234', 'US');
test('98765432101', 'IN');
