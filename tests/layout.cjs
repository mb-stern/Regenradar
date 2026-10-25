// Real browser layout test. Generate HTML with tests/module.php first.
// node tests/layout.cjs /tmp/regenradar-test.js.html /tmp/leaflet.js /tmp/leaflet.css
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    let moduleHtml = fs.readFileSync(process.argv[2] || '/tmp/regenradar-test.js.html', 'utf8');
    const leaflet = fs.readFileSync(process.argv[3] || '/tmp/leaflet.js', 'utf8');
    const leafletCss = fs.readFileSync(process.argv[4] || '/tmp/leaflet.css', 'utf8');
    const pixel = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#e6eadf"/><path d="M0 128H256M128 0V256" stroke="#d4ddcb"/></svg>');
    moduleHtml = moduleHtml.replace(/<script src="[^"]*"><\/script>/g, '');
    moduleHtml = moduleHtml.replace(/const WR_INITIAL = (.*);/, (_, json) => {
        const data = JSON.parse(json);
        data.data.config.mapTileUrl = pixel;
        data.data.config.lat = 47.3; data.data.config.lon = 8.5;
        return 'const WR_INITIAL = ' + JSON.stringify(data) + ';';
    });
    const html = `<html><head><style>html,body{margin:0}#host{width:1000px;height:600px}${leafletCss}</style></head><body>
<script>function requestAction(){}</script><script>${leaflet}</script><div id="host">${moduleHtml}</div></body></html>`;
    const browser = await (await fetch('http://127.0.0.1:9222/json/version')).json();
    const ws = new WebSocket(browser.webSocketDebuggerUrl);
    await new Promise(resolve => ws.addEventListener('open', resolve, {once: true}));
    let id = 0, target;
    const pending = new Map();
    ws.addEventListener('message', e => {
        const message = JSON.parse(e.data);
        if (pending.has(message.id)) { pending.get(message.id)(message); pending.delete(message.id); }
    });
    const send = (method, params = {}, sessionId) => new Promise(resolve => {
        pending.set(++id, resolve); ws.send(JSON.stringify({id, method, params, sessionId}));
    });
    try {
        target = (await send('Target.createTarget', {url: 'about:blank'})).result.targetId;
        const session = (await send('Target.attachToTarget', {targetId: target, flatten: true})).result.sessionId;
        await send('Emulation.setDeviceMetricsOverride', {width: 1800, height: 1100, deviceScaleFactor: 1, mobile: false}, session);
        await send('Network.enable', {}, session);
        await send('Network.setBlockedURLs', {urls: ['http://*', 'https://*']}, session);
        const frame = (await send('Page.getFrameTree', {}, session)).result.frameTree.frame.id;
        await send('Page.setDocumentContent', {frameId: frame, html}, session);
        async function evaluate(expression) {
            const response = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true}, session);
            if (response.result?.exceptionDetails) throw new Error(JSON.stringify(response.result.exceptionDetails));
            return response.result?.result?.value;
        }
        await evaluate(`wrRenderWeather({current:{temperature:'−12,3 °C',humidity:'100 %',wind:'123,4 km/h',rain:'123,4 mm',clouds:'100 %'},forecast:Array.from({length:7},(_,i)=>({dt:1761408000+i*86400,max:17,min:-12,icon:'04d'}))});
            document.querySelectorAll('img').forEach(img=>img.src='${pixel}');wrSetText('wr-frame-time','20:40:00');`);
        const results = [];
        for (const [width, height] of [[1000,600],[320,320],[240,160],[320,180],[600,240],[720,420],[360,640],[1600,900],[600,400],[1000,600]]) {
            const state = await evaluate(`(async()=>{
                const host=document.getElementById('host');host.style.width='${width}px';host.style.height='${height}px';
                await new Promise(r=>setTimeout(r,100));
                const root=document.getElementById('wetterradar-root'), bounds=root.getBoundingClientRect();
                const rect=el=>{const r=el.getBoundingClientRect();return {x:r.x-bounds.x,y:r.y-bounds.y,w:r.width,h:r.height};};
                const panels=['wr-current','wr-controls','wr-legend','wr-forecast'].map(id=>({id,...rect(document.getElementById(id))})).filter(r=>r.w&&r.h);
                return {width:root.clientWidth,height:root.clientHeight,compact:root.classList.contains('wr-compact'),tiny:root.classList.contains('wr-tiny'),panels,
                layoutStyle:getComputedStyle(document.querySelector('.wr-layout')).display, layoutHeight:document.querySelector('.wr-layout').getBoundingClientRect().height, space:document.querySelector('.wr-map-space').getBoundingClientRect().height,mapSize:wrMap.getSize(),center:wrMap.getCenter(),
                font:parseFloat(getComputedStyle(document.getElementById('wr-current')).fontSize)};
            })()`);
            assert.equal(state.width, width); assert.equal(state.height, height);
            assert.equal(state.compact, width < 720 || height < 480);
            assert.equal(state.mapSize.x, width); assert.equal(state.mapSize.y, height);
            assert.ok(state.font >= 11, 'Weather text must stay readable');
            assert.ok(state.space >= 12, 'Keep usable map space '+width+'x'+height+': '+JSON.stringify(state));
            assert.ok(Math.abs(state.center.lat - 47.3) < 0.00001 && Math.abs(state.center.lng - 8.5) < 0.00001, 'Resizing must preserve map center');
            for (const panel of state.panels) {
                assert.ok(panel.x >= -1 && panel.y >= -1 && panel.x + panel.w <= width + 1 && panel.y + panel.h <= height + 1, `Panel outside ${width}x${height}: ${JSON.stringify(panel)}`);
                for (const other of state.panels) {
                    if (panel.id >= other.id) continue;
                    const overlapX = Math.min(panel.x+panel.w,other.x+other.w)-Math.max(panel.x,other.x);
                    const overlapY = Math.min(panel.y+panel.h,other.y+other.h)-Math.max(panel.y,other.y);
                    assert.ok(overlapX <= 1 || overlapY <= 1, `Overlap ${width}x${height}: ${panel.id}/${other.id}`);
                }
            }
            results.push({size:`${width}x${height}`,mapSpace:state.space});
            if(width===360 || width===1000) {const shot=await send('Page.captureScreenshot',{format:'png',clip:{x:0,y:0,width,height,scale:1}},session);fs.writeFileSync('/tmp/regenradar-layout-'+width+'.png',Buffer.from(shot.result.data,'base64'));}
        }
        console.log(JSON.stringify(results));
        // Compact disclosures remain usable and the hidden forecasts stay accessible.
        await evaluate(`document.getElementById('host').style.width='240px';document.getElementById('host').style.height='160px';new Promise(r=>setTimeout(r,100))`);
        for (const selector of ['.wr-legend-toggle','.wr-forecast-toggle']) {
            const open = await evaluate(`(()=>{const button=document.querySelector('${selector}');button.click();const target=document.getElementById(button.getAttribute('aria-controls'));const r=target.getBoundingClientRect();return {expanded:button.getAttribute('aria-expanded'),w:r.width,h:r.height,top:r.top,bottom:r.bottom};})()`);
            assert.equal(open.expanded,'true'); assert.ok(open.w > 0 && open.h > 0);
            assert.ok(open.top >= 0 && open.bottom <= 160, 'Disclosure must stay in the tile');
            await evaluate(`document.querySelector('${selector}').click()`);
        }
        // Hide/reveal must invalidate a map recreated while the tile had zero size.
        await evaluate(`(async()=>{const host=document.getElementById('host');host.style.display='none';await new Promise(r=>setTimeout(r,80));wrHandlePayload({type:'reload',data:{config:wrConfig,weather:{},radar:{provider:'rainbow',frames:[]}}});host.style.display='block';await new Promise(r=>setTimeout(r,100));})()`);
        const revealed = await evaluate(`({size:wrMap.getSize(),expected:{x:document.getElementById('host').clientWidth,y:document.getElementById('host').clientHeight}})`);
        assert.deepEqual(revealed.size,revealed.expected);
        // Debug is optional and must stay between the normal panels.
        const debug = await evaluate(`(()=>{wrConfig.showTileDebug=true;wrSetTileDebugVisible();const box=document.getElementById('wr-tile-debug');return {top:box.getBoundingClientRect().top,bottom:box.getBoundingClientRect().bottom,controlsBottom:document.getElementById('wr-controls').getBoundingClientRect().bottom,legendTop:document.getElementById('wr-legend').getBoundingClientRect().top};})()`);
        assert.ok(debug.top >= debug.controlsBottom && debug.bottom <= debug.legendTop, JSON.stringify(debug));
        console.log('PASS root-only resizing, panel bounds/non-overlap, center, hidden/revealed maps, debug and compact disclosures');
    } finally {
        if (target) await send('Target.closeTarget', {targetId: target});
        ws.close();
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
