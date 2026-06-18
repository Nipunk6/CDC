const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });

console.log(regionNames.of('US'));
console.log(regionNames.of('IN'));
console.log(regionNames.of('BB'));
console.log(regionNames.of('GB'));
