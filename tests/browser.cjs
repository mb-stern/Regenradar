// Requires a local Chromium CDP endpoint and a downloaded Leaflet 1.9.4 script.
// node tests/browser.cjs /tmp/regenradar-test.js /tmp/leaflet.js
const {readFileSync} = require('node:fs');
(async () => {
    const source = readFileSync(process.argv[2] || '/tmp/regenradar-test.js', 'utf8');
    const functions = source.slice(source.indexOf('function wrRadarColor('), source.indexOf('// Leaflet imageOverlay'));
    const tileFunctions = source.slice(source.indexOf('function wrBuildRadarLayer('), source.indexOf('function wrRadarCacheKey('));
    const legendFunctions = source.slice(source.indexOf('function wrEscapeHtml('), source.indexOf('function wrRenderWeather('));
    const payloadFunction = source.slice(source.indexOf('function wrHandlePayload('), source.indexOf('function handleMessage('));
    const palette = readFileSync(__dirname + '/../RGR/radar-palette.json', 'utf8');
    const leaflet = readFileSync(process.argv[3] || '/tmp/leaflet.js', 'utf8');
    const html = `<html><body><pre id="result">PENDING</pre><div id="wr-legend"></div><span id="wr-frame-time"></span><script>${leaflet}</script><script>
let wrConfig={radarPalette:${palette},radarProvider:"rainviewer",radarMaxZoom:18};
${functions}
${tileFunctions}
${legendFunctions}
${payloadFunction}
const wrRadarPayload={provider:'rainbow'};
function wrRadarCacheKey(){return 'test';} function wrAttachTileDebug(layer){return layer;}
let wrData, wrBaseLayer, removed=0, refreshes=0, actions=[], failAction=false;
const initialCenter={lat:47.3,lng:8.5};
function makeMap() {return {getCenter:()=>initialCenter,getZoom:()=>15,remove:()=>removed++,setView:(center,zoom)=>{
 if(center.lat!==initialCenter.lat || center.lng!==initialCenter.lng || zoom!==15)throw Error('Map position lost');
}};}
let wrMap=makeMap();
function wrStopAnimation() {} function wrStopRadarRefreshTimer() {}
function wrStartRadarRefreshTimer() { refreshes++; }
function wrClearRadarLayers() {} function wrSetTileDebugVisible() {}
function wrInitMap() {wrMap=makeMap();} function wrRenderWeather() {} function wrRenderRadar() {}
function wrSetText(id,text) {document.getElementById(id).textContent=text;}
function requestAction(ident,value) {if(failAction)throw Error('Test transport failure');actions.push([ident,value]);}
function testProviders() {
 const legend={type:'entries',provider:'RainViewer',title:'Universal Blue',entries:[],note:'Approximate'};
 wrRenderLegend(legend);
 for(const provider of ['rainbow','meteoswiss','rainviewer']) {
  const select=document.querySelector('.wr-provider-select');select.value=provider;select.dispatchEvent(new Event('change'));
  if(!select.disabled || JSON.stringify(actions.at(-1))!==JSON.stringify(['SetRadarProvider',provider]))throw Error('Switch action missing');
  wrHandlePayload({type:'reload',data:{config:{...wrConfig,radarProvider:provider,legend},weather:{},radar:{provider,frames:[]}}});
  const updated=document.querySelector('.wr-provider-select');
  if(updated.value!==provider || updated.disabled)throw Error('Selection not restored');
 }
 if(removed!==3 || refreshes!==3)throw Error('Reload lifecycle incomplete');
 failAction=true;const select=document.querySelector('.wr-provider-select');select.value='rainbow';select.dispatchEvent(new Event('change'));
 if(select.disabled || select.value!=='rainviewer' || !document.getElementById('wr-frame-time').textContent.includes('fehlgeschlagen'))throw Error('Switch recovery failed');
}
const input=document.createElement('canvas');input.width=3;input.height=1;
const ctx=input.getContext('2d'), pixels=ctx.createImageData(3,1);
pixels.data.set([193,0,0,255,193,0,0,255,0,0,0,0]);ctx.putImageData(pixels,0,0);
const layer=wrBuildRadarLayer({time:1,url:input.toDataURL()});layer._tileZoom=0;
layer.createTile({x:0,y:0,z:0},(error,tile)=>{
 try {
  if(error)throw error;
  if(!(tile instanceof HTMLImageElement) || tile.crossOrigin!==null)throw Error('Rainbow must use native image tiles without Canvas/CORS');
  testProviders();
  document.getElementById('result').textContent='PASS real Chromium + Leaflet native Rainbow tiles and provider selection/reload/recovery';
 } catch(e){document.getElementById('result').textContent='FAIL '+e.message;}
});
</script></body></html>`;
    const base = process.argv[4] || 'http://127.0.0.1:9222';
    const browser = await (await fetch(base + '/json/version')).json();
    const ws = new WebSocket(browser.webSocketDebuggerUrl);
    await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
    let id = 0;
    const pending = new Map();
    ws.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (pending.has(message.id)) {
            pending.get(message.id)(message);
            pending.delete(message.id);
        }
    });
    const send = (method, params = {}, sessionId) => new Promise(resolve => {
        pending.set(++id, resolve);
        ws.send(JSON.stringify({id, method, params, sessionId}));
    });
    let target;
    try {
        const created = await send('Target.createTarget', {url: 'about:blank'});
        target = created.result.targetId;
        const attached = await send('Target.attachToTarget', {targetId: target, flatten: true});
        const session = attached.result.sessionId;
        const frame = await send('Page.getFrameTree', {}, session);
        await send('Page.setDocumentContent', {frameId: frame.result.frameTree.frame.id, html}, session);
        for (let i = 0; i < 40; i++) {
            await new Promise(resolve => setTimeout(resolve, 250));
            const result = await send('Runtime.evaluate', {expression: "document.getElementById('result')?.textContent", returnByValue: true}, session);
            const value = result.result?.result?.value;
            if (value && value !== 'PENDING') {
                if (!value.startsWith('PASS')) throw new Error(value);
                console.log(value);
                return;
            }
        }
        throw new Error('Browser rendering timed out');
    } finally {
        if (target) await send('Target.closeTarget', {targetId: target});
        ws.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
