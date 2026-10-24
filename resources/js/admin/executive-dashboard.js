const compact = new Intl.NumberFormat('vi-VN', {notation:'compact',maximumFractionDigits:1});
const exact = new Intl.NumberFormat('vi-VN');
function render(container, rows, series) {
    const svg=container.querySelector('svg'), empty=container.querySelector('.executive-chart__empty'), tip=container.querySelector('.executive-tooltip');
    if(!svg||!empty||!tip||rows.length<2){if(empty)empty.hidden=false;return}
    const w=Math.max(320,container.clientWidth),h=Math.max(240,container.clientHeight),p={l:28,r:18,t:22,b:38},ns='http://www.w3.org/2000/svg',max=Math.max(1,...rows.flatMap(r=>series.map(s=>Math.abs(Number(r[s.key])||0)))),x=i=>p.l+i*((w-p.l-p.r)/Math.max(1,rows.length-1)),y=v=>p.t+(h-p.t-p.b)*(1-(Number(v)||0)/max);
    svg.replaceChildren();svg.setAttribute('viewBox',`0 0 ${w} ${h}`);
    [0,.25,.5,.75,1].forEach(r=>{const l=document.createElementNS(ns,'line'),ly=p.t+r*(h-p.t-p.b);Object.entries({x1:p.l,x2:w-p.r,y1:ly,y2:ly}).forEach(([k,v])=>l.setAttribute(k,v));l.classList.add('executive-chart__grid');svg.append(l)});
    series.forEach(s=>{const path=document.createElementNS(ns,'path');path.setAttribute('d',rows.map((r,i)=>`${i?'L':'M'} ${x(i)} ${y(r[s.key])}`).join(' '));path.setAttribute('class',`executive-chart__line ${s.className}`);svg.append(path)});
    const cross=document.createElementNS(ns,'line');cross.setAttribute('class','executive-chart__crosshair');cross.setAttribute('y1',p.t);cross.setAttribute('y2',h-p.b);cross.hidden=true;svg.append(cross);
    rows.forEach((row,i)=>{const hit=document.createElementNS(ns,'rect');Object.entries({x:x(i)-14,y:p.t,width:28,height:h-p.t-p.b}).forEach(([k,v])=>hit.setAttribute(k,v));hit.classList.add('executive-chart__hit');const activate=()=>{cross.hidden=false;cross.setAttribute('x1',x(i));cross.setAttribute('x2',x(i));svg.querySelectorAll('.executive-chart__point').forEach(n=>n.remove());series.forEach(s=>{const c=document.createElementNS(ns,'circle');c.setAttribute('cx',x(i));c.setAttribute('cy',y(row[s.key]));c.setAttribute('r',5);c.setAttribute('class',`executive-chart__point ${s.className}`);svg.append(c)});tip.hidden=false;tip.textContent=`${row.period} · ${series.map(s=>`${s.label}: ${exact.format(Number(row[s.key])||0)} ₫`).join(' · ')}`;tip.style.left=`${Math.min(w-220,Math.max(6,x(i)-90))}px`};hit.addEventListener('pointerenter',activate);hit.addEventListener('focus',activate);hit.addEventListener('pointerleave',()=>{cross.hidden=true;tip.hidden=true});svg.append(hit);if(i===0||i===rows.length-1||rows.length<=6){const t=document.createElementNS(ns,'text');t.textContent=row.period;t.setAttribute('x',x(i));t.setAttribute('y',h-10);t.classList.add('executive-chart__label');svg.append(t)}});
}
function renderBars(container, rows, series, labelKey='branch') {
    if (!container) return;
    container.replaceChildren();
    if (!rows.length) { container.textContent='Chưa đủ bằng chứng trong phạm vi đã chọn.'; container.classList.add('is-empty'); return; }
    const max=Math.max(1,...rows.flatMap(row=>series.map(item=>Math.abs(Number(row[item.key])||0))));
    rows.forEach(row=>{
        const group=document.createElement('div'); group.className='executive-bar-group'; group.tabIndex=0;
        const label=document.createElement('strong'); label.textContent=String(row[labelKey]||'—'); group.append(label);
        series.forEach(item=>{
            const line=document.createElement('div'); line.className='executive-bar-line';
            const name=document.createElement('span'); name.textContent=item.label;
            const track=document.createElement('i'); const bar=document.createElement('b');
            const value=Number(row[item.key])||0; bar.className=`${item.className||'is-gold'} ${value<0?'is-negative':''}`; bar.style.width=`${Math.max(value===0?0:2,Math.abs(value)/max*100)}%`; track.append(bar);
            const amount=document.createElement('em'); amount.textContent=`${exact.format(value)} ₫`; amount.title=`${row[labelKey]} · ${item.label}: ${exact.format(value)} ₫`;
            line.append(name,track,amount); group.append(line);
        }); group.title=series.map(item=>`${row[labelKey]} · ${item.label}: ${exact.format(Number(row[item.key])||0)} ₫`).join(' · '); container.append(group);
    });
}
export function bootExecutiveDashboards(){document.querySelectorAll('[data-executive-dashboard]').forEach(root=>{let financial=[],cash=[],branches=[],budget=[],outlook=[];try{financial=JSON.parse(root.dataset.financial||'[]');cash=JSON.parse(root.dataset.cash||'[]');branches=JSON.parse(root.dataset.branches||'[]');budget=JSON.parse(root.dataset.budget||'[]');outlook=JSON.parse(root.dataset.outlook||'[]')}catch{return}let branchMetric='revenue';const draw=()=>{const f=root.querySelector('[data-chart="financial"]'),c=root.querySelector('[data-chart="cash"]');if(f)render(f,financial,[{key:'revenue',label:'Doanh thu',className:'is-gold'}]);if(c)render(c,cash,[{key:'inflow',label:'Tiền vào',className:'is-success'},{key:'outflow',label:'Tiền ra',className:'is-danger'}]);renderBars(root.querySelector('[data-chart="branches"]'),[...branches].sort((a,b)=>(Number(b[branchMetric])||0)-(Number(a[branchMetric])||0)),[{key:branchMetric,label:{revenue:'Doanh thu',gross_profit:'Lợi nhuận gộp',operating_result:'Kết quả vận hành'}[branchMetric],className:'is-gold'}]);renderBars(root.querySelector('[data-chart="budget"]'),budget,[{key:'budget',label:'Budget',className:'is-gold'},{key:'actual',label:'Actual',className:'is-danger'},{key:'commitment',label:'Commitment',className:'is-warning'}]);renderBars(root.querySelector('[data-chart="outlook"]'),outlook,[{key:'receivable',label:'AR inflow',className:'is-success'},{key:'payable',label:'AP outflow',className:'is-danger'}],'label')};root.querySelectorAll('[data-branch-metric]').forEach(button=>button.addEventListener('click',()=>{branchMetric=button.dataset.branchMetric;root.querySelectorAll('[data-branch-metric]').forEach(item=>item.classList.toggle('is-active',item===button));draw()}));draw();new ResizeObserver(draw).observe(root)})}
