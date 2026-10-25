const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const palette = JSON.parse(readFileSync(__dirname + '/../RGR/radar-palette.json', 'utf8'));
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
    wrAnimationTimer: null, wrConfig: {enableAutoplay: true, radarPalette: palette},
    wrStopAnimation() {}, wrPruneRadarLayerCache() {}, wrSetText() {},
    wrRadarCacheKey(f) { return String(f.time); }, wrShowFrame() {},
    wrPlayAnimation() { calls.push(['play']); }
});
vm.runInContext(extract('function wrMeteoswissLatitude(', 'async function wrBuildMeteoswissImage('), context);
vm.runInContext(extract('function wrLv95FromWgs84(', 'function wrMeteoswissColor('), context);
vm.runInContext(extract('function wrRadarColor(', '// Leaflet imageOverlay'), context);
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

// Official Universal Blue sample points, not the former invented intensity bins.
assert.equal(palette.colors.length, 128);
for (const [dbz, rgba] of [[15, [136,221,238,255]], [35, [255,238,0,255]], [50, [193,0,0,255]], [60, [255,119,255,255]]]) {
    assert.deepEqual(Array.from(context.wrRadarColor(dbz)), rgba);
    const rate = Math.pow(Math.pow(10, dbz / 10) / 200, 1 / 1.6);
    assert.deepEqual(Array.from(context.wrMeteoswissColor(rate)), rgba, 'mm/h must use the same palette after Z-R conversion');
}
console.log('PASS official palette and physical conversion');

const tileEvents = {};
const tileLayer = {on(name, callback) { tileEvents[name] = callback; }};
context.wrRadarLayer = tileLayer;
context.wrTileDebugStats = {};
context.wrRadarLoadedAtCache = {};
context.wrUpdateTileDebug = () => {};
context.wrFrameTime = () => '20:50:00';
const statusMessages = [];
context.wrSetText = (id, value) => statusMessages.push(value);
vm.runInContext(extract('function wrAttachTileDebug(', 'function wrGetH5Module('), context);
context.wrAttachTileDebug(tileLayer, {time:1}, 'tile', 'test');
tileEvents.loading();tileEvents.tileerror();tileEvents.load();
assert.equal(tileLayer.wrLoadError, true);
assert.ok(statusMessages.at(-1).includes('nicht erreichbar'), 'Failed tile set must not appear as dry weather');
tileEvents.loading();tileEvents.tileload();tileEvents.load();
assert.equal(tileLayer.wrLoadError, false);
assert.equal(statusMessages.at(-1), '20:50:00', 'Successful retry clears the error');
console.log('PASS tile failure visibility and recovery');
