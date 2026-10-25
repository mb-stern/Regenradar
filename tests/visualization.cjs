const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = readFileSync(process.argv[2] || '/tmp/regenradar-test.js', 'utf8');
function extract(start, end) { return source.slice(source.indexOf(start), source.indexOf(end, source.indexOf(start))); }
const calls = [];
const context = vm.createContext({
    Math, Number, console,
    document: {visibilityState: 'hidden', getElementById() { return null; }},
    wrMap: {attributionControl: {
        addAttribution(value) { calls.push(['add', value]); },
        removeAttribution(value) { calls.push(['remove', value]); }
    }},
    wrAnimationTimer: null, wrConfig: {enableAutoplay: true},
    wrStopAnimation() {}, wrPruneRadarLayerCache() {}, wrSetText() {},
    wrRadarCacheKey(f) { return String(f.time); }, wrShowFrame() {},
    wrPlayAnimation() { calls.push(['play']); }
});
vm.runInContext(extract('function wrMeteoswissLatitude(', 'async function wrBuildMeteoswissImage('), context);
vm.runInContext(extract('function wrLv95FromWgs84(', 'function wrMeteoswissColor('), context);
vm.runInContext(extract('function wrMeteoswissColor(', '// Leaflet imageOverlay'), context);
vm.runInContext(extract('function wrRenderRadar(', 'function wrHandlePayload('), context);
const lat = context.wrMeteoswissLatitude(0, 1, 43.619, 49.3744);
const mercator = d => Math.log(Math.tan(Math.PI / 4 + d * Math.PI / 360));
assert.ok(Math.abs(mercator(lat) - (mercator(43.619) + mercator(49.3744)) / 2) < 1e-12);
assert.ok(Math.abs(lat - (43.619 + 49.3744) / 2) > 0.05, 'must correct linear latitude interpolation');
const [east, north] = context.wrLv95FromWgs84(46.95108, 7.438637);
assert.ok(Math.abs(east - 2600000) < 2 && Math.abs(north - 1200000) < 2, 'Swiss projection reference');
for (const n of [NaN, 0, -9999]) assert.deepEqual(Array.from(context.wrMeteoswissColor(n)), [0, 0, 0, 0]);
context.wrRenderRadar({provider: 'rainviewer', frames: [{time: 1}, {time: 2}]});
assert.equal(context.wrFrameIndex, 1);
assert.ok(calls.some(([op, value]) => op === 'add' && value.includes('https://www.rainviewer.com/')));
assert.ok(!calls.some(([op]) => op === 'play'), 'hidden visualization must not autoplay');
context.document.visibilityState = 'visible';
context.wrRenderRadar({provider: 'rainbow', snapshot: 2, frames: [{time: 1}, {time: 2}, {time: 3}]});
assert.equal(context.wrFrameIndex, 1, 'Rainbow starts at current snapshot');
assert.ok(calls.some(([op]) => op === 'play'));
assert.ok(calls.some(([op, value]) => op === 'remove' && value.includes('rainviewer.com')));
context.wrRenderRadar({provider: 'meteoswiss', frames: []});
assert.equal(context.wrFrames.length, 0);
console.log('PASS Mercator sampling, Swiss projection, nodata colors, attribution, frame selection and visibility');
