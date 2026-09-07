<?php // index.php — GymPro Landing Page v3 ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>GymPro — Smart Gym Management System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="GymPro — Professional gym management for admins, trainers and members. Bookings, payments, diet plans and more.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;0,900;1,800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   TOKENS — DARK MODE (default)
═══════════════════════════════════════════════ */
[data-theme="dark"] {
  --bg:           #07090c;
  --bg-2:         #0d1017;
  --bg-3:         #141820;
  --card:         #111520;
  --card-hover:   #181d2a;
  --border:       rgba(255,255,255,0.07);
  --border-hi:    rgba(200,241,53,0.3);
  --text-1:       #f0f4ff;
  --text-2:       #8a95b0;
  --text-3:       #4a5268;
  --accent:       #c8f135;
  --accent-dim:   #a5cc1f;
  --accent-glow:  rgba(200,241,53,0.12);
  --accent-glow2: rgba(200,241,53,0.06);
  --hero-overlay: linear-gradient(120deg,rgba(7,9,12,0.97) 0%,rgba(7,9,12,0.75) 55%,rgba(7,9,12,0.9) 100%);
  --nav-bg:       rgba(7,9,12,0.82);
  --btn-dark-text:#07090c;
  --outline-border:rgba(255,255,255,0.18);
  --feat-bg:      #0f1420;
  --stat-border:  rgba(255,255,255,0.06);
  --shadow-xl:    0 32px 80px rgba(0,0,0,0.7);
  --shadow-card:  0 8px 32px rgba(0,0,0,0.5);
  --step-bg:      #0f1420;
  --tag-color:    var(--accent);
  --lime-text:    filter:brightness(1);
  --stroke-color: var(--text-1);
}

/* ═══════════════════════════════════════════════
   TOKENS — LIGHT MODE
═══════════════════════════════════════════════ */
[data-theme="light"] {
  --bg:           #f8fafc;
  --bg-2:         #ffffff;
  --bg-3:         #f1f5fb;
  --card:         #ffffff;
  --card-hover:   #f7faff;
  --border:       #e2e8f4;
  --border-hi:    #9bbb28;
  --text-1:       #0c1228;
  --text-2:       #3d4a6b;
  --text-3:       #7b8aaa;
  --accent:       #7ab800;
  --accent-dim:   #5e9200;
  --accent-glow:  rgba(122,184,0,0.12);
  --accent-glow2: rgba(122,184,0,0.06);
  --hero-overlay: linear-gradient(120deg,rgba(248,250,252,0.97) 0%,rgba(248,250,252,0.78) 50%,rgba(248,250,252,0.94) 100%);
  --nav-bg:       rgba(255,255,255,0.9);
  --btn-dark-text:#ffffff;
  --outline-border:rgba(12,18,40,0.22);
  --feat-bg:      #ffffff;
  --stat-border:  #e2e8f4;
  --shadow-xl:    0 32px 80px rgba(12,18,40,0.12);
  --shadow-card:  0 8px 32px rgba(12,18,40,0.08);
  --step-bg:      #f1f5fb;
  --tag-color:    var(--accent);
  --stroke-color: var(--text-1);
}

/* ═══════════════════════════════════════════════
   BASE
═══════════════════════════════════════════════ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
body {
  font-family:'DM Sans',sans-serif;
  background:var(--bg); color:var(--text-1);
  overflow-x:hidden;
  transition:background .35s,color .35s;
}
a { text-decoration:none; }
img { display:block; }

::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-track { background:var(--bg); }
::-webkit-scrollbar-thumb { background:var(--accent); border-radius:4px; }

/* ═══════════════════════════════════════════════
   UTILITY — REVEAL
═══════════════════════════════════════════════ */
.reveal { opacity:0; transform:translateY(36px); transition:opacity .65s ease,transform .65s ease; }
.reveal.in { opacity:1; transform:none; }
.d1{transition-delay:.08s} .d2{transition-delay:.16s} .d3{transition-delay:.24s} .d4{transition-delay:.32s}

/* ═══════════════════════════════════════════════
   SHARED BUTTONS
═══════════════════════════════════════════════ */
.btn-lime {
  display:inline-flex; align-items:center; gap:8px;
  padding:14px 30px; border-radius:10px;
  background:var(--accent); color:var(--btn-dark-text);
  font-family:'DM Sans',sans-serif; font-size:14px; font-weight:700;
  transition:all .2s; border:none; cursor:pointer;
}
.btn-lime:hover { background:var(--accent-dim); transform:translateY(-2px); box-shadow:0 10px 28px var(--accent-glow); }
.btn-ghost {
  display:inline-flex; align-items:center; gap:8px;
  padding:14px 30px; border-radius:10px;
  background:transparent; color:var(--text-1);
  border:1px solid var(--outline-border);
  font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600;
  transition:all .2s; cursor:pointer;
}
.btn-ghost:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-glow); }

/* ═══════════════════════════════════════════════
   NAVBAR
═══════════════════════════════════════════════ */
.nav {
  position:fixed; top:0; left:0; right:0; z-index:1000;
  height:66px;
  display:flex; align-items:center; justify-content:space-between;
  padding:0 40px;
  background:var(--nav-bg);
  backdrop-filter:blur(20px) saturate(1.4);
  border-bottom:1px solid var(--border);
  transition:background .3s,border-color .3s;
}
.nav.stuck { border-bottom-color:var(--border-hi); }

.nav-logo {
  font-family:'Barlow Condensed',sans-serif;
  font-size:1.65rem; font-weight:900; letter-spacing:.02em;
  color:var(--text-1);
}
.nav-logo em { color:var(--accent); font-style:normal; }

.nav-links { display:flex; align-items:center; gap:28px; }
.nav-links a {
  font-size:14px; font-weight:500; color:var(--text-2);
  transition:color .2s; padding:4px 0;
  border-bottom:2px solid transparent;
  transition:color .2s,border-color .2s;
}
.nav-links a:hover { color:var(--text-1); border-bottom-color:var(--accent); }

.nav-right { display:flex; align-items:center; gap:10px; }

/* Theme Toggle */
.theme-btn {
  width:40px; height:40px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  background:var(--bg-3); border:1px solid var(--border);
  cursor:pointer; font-size:17px;
  transition:all .25s; flex-shrink:0;
}
.theme-btn:hover { border-color:var(--accent); background:var(--accent-glow); transform:rotate(15deg); }
.theme-btn .sun { display:none; }
.theme-btn .moon { display:block; }
[data-theme="light"] .theme-btn .sun  { display:block; }
[data-theme="light"] .theme-btn .moon { display:none; }

.nav-login {
  padding:8px 20px; border-radius:8px;
  border:1px solid var(--border); background:transparent;
  color:var(--text-2); font-size:14px; font-weight:600;
  transition:all .2s;
}
.nav-login:hover { color:var(--text-1); border-color:var(--border-hi); }
.nav-cta {
  padding:9px 20px; border-radius:8px;
  background:var(--accent); color:var(--btn-dark-text);
  font-size:14px; font-weight:700;
  transition:all .2s;
}
.nav-cta:hover { background:var(--accent-dim); transform:translateY(-1px); }

/* Hamburger & Mobile Toggles */
.mob-menu { display:none; align-items:center; gap:10px; }
.hamburger { display:flex; flex-direction:column; gap:5px; cursor:pointer; background:none; border:none; padding:4px; }
.hamburger span { display:block; width:22px; height:2px; background:var(--text-1); border-radius:2px; transition:all .3s; }
.hamburger.open span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity:0; transform:scaleX(0); }
.hamburger.open span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

/* Mobile drawer */
.mob-nav {
  display:none; position:fixed; top:66px; left:0; right:0;
  background:var(--bg-2); border-bottom:1px solid var(--border);
  padding:20px 28px 28px; z-index:998;
  flex-direction:column; gap:4px;
}
.mob-nav.open { display:flex; }
.mob-nav a {
  padding:13px 0; font-size:15px; font-weight:500; color:var(--text-2);
  border-bottom:1px solid var(--border); transition:color .2s;
}
.mob-nav a:hover { color:var(--accent); }
.mob-nav .mob-actions { display:flex; gap:10px; margin-top:18px; }
.mob-nav .mob-actions a { border:none; padding:0; flex:1; }
.mob-nav .mob-actions .btn-lime,
.mob-nav .mob-actions .btn-ghost { width:100%; justify-content:center; padding:12px; }

/* ═══════════════════════════════════════════════
   HERO
═══════════════════════════════════════════════ */
.hero {
  min-height:100vh; position:relative;
  display:flex; align-items:center;
  padding:130px 40px 90px;
  overflow:hidden;
}
.hero-photo {
  position:absolute; inset:0;
  background:var(--hero-overlay),
    url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1800&q=80&fm=jpg') center/cover no-repeat;
  will-change:transform;
  transition:filter .35s;
}
[data-theme="light"] .hero-photo { filter:brightness(0.92) saturate(0.85); }

/* orbs */
.orb {
  position:absolute; border-radius:50%;
  filter:blur(110px); pointer-events:none; z-index:1;
  animation:drift 18s ease-in-out infinite alternate;
}
.o1 { width:600px; height:600px; top:-160px; right:-120px; background:radial-gradient(circle,rgba(200,241,53,0.07) 0%,transparent 65%); }
.o2 { width:480px; height:480px; bottom:-120px; left:-80px;  background:radial-gradient(circle,rgba(200,241,53,0.05) 0%,transparent 65%); animation-delay:9s; }
@keyframes drift { from{transform:translate(0,0) scale(1);} to{transform:translate(50px,35px) scale(1.12);} }

.hero-inner {
  position:relative; z-index:2;
  max-width:1200px; margin:0 auto; width:100%;
  display:grid; grid-template-columns:1fr 420px; gap:60px; align-items:center;
}

/* Left */
.eyebrow {
  display:inline-flex; align-items:center; gap:8px;
  padding:6px 14px; border-radius:999px;
  background:var(--accent-glow); border:1px solid var(--border-hi);
  font-size:11px; font-weight:700; color:var(--accent);
  letter-spacing:.09em; text-transform:uppercase; margin-bottom:22px;
  opacity:0; animation:up .6s .1s forwards;
}
.dot { width:6px; height:6px; border-radius:50%; background:var(--accent); animation:blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.5)} }

.hero-h1 {
  font-family:'Barlow Condensed',sans-serif;
  font-size:clamp(3.8rem,8.5vw,7.5rem);
  font-weight:900; line-height:.88; text-transform:uppercase;
  letter-spacing:-.01em; color:var(--text-1);
  margin-bottom:26px;
  opacity:0; animation:up .7s .2s forwards;
}
.hero-h1 .lime  { color:var(--accent); display:block; }
.hero-h1 .ghost { color:transparent; -webkit-text-stroke:2px var(--stroke-color); display:block; }
[data-theme="light"] .hero-h1 .ghost {
  color:var(--text-1); -webkit-text-stroke:0;
  background:linear-gradient(180deg,transparent 60%,rgba(122,184,0,0.2) 60%);
  padding:0 4px; display:inline;
}

.hero-p {
  font-size:16px; line-height:1.75; color:var(--text-2);
  max-width:490px; margin-bottom:36px;
  opacity:0; animation:up .7s .3s forwards;
}
.hero-btns {
  display:flex; gap:12px; flex-wrap:wrap;
  opacity:0; animation:up .7s .4s forwards;
}
.hero-counters {
  display:flex; gap:36px; flex-wrap:wrap; margin-top:52px;
  opacity:0; animation:up .7s .5s forwards;
}
.hc { text-align:left; }
.hc-num { font-family:'Barlow Condensed',sans-serif; font-size:2.4rem; font-weight:800; color:var(--accent); line-height:1; }
.hc-label { font-size:11px; color:var(--text-3); text-transform:uppercase; letter-spacing:.07em; margin-top:3px; }

@keyframes up { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:none} }

/* Right Panel */
.hero-panel {
  background:color-mix(in srgb,var(--card) 80%,transparent);
  border:1px solid var(--border);
  border-radius:22px; overflow:hidden;
  backdrop-filter:blur(16px);
  box-shadow:var(--shadow-xl);
  opacity:0; animation:fromRight .8s .35s forwards;
}
[data-theme="light"] .hero-panel { background:rgba(255,255,255,0.88); }
@keyframes fromRight { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:none} }

.hero-panel img { width:100%; height:210px; object-fit:cover; filter:brightness(.8) saturate(.85); }
.panel-body { padding:24px; }
.panel-eyebrow { font-size:10px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--accent); margin-bottom:14px; }
.role-row {
  display:flex; align-items:center; gap:12px;
  padding:13px 14px; border-radius:10px;
  background:var(--accent-glow2); border:1px solid var(--border);
  margin-bottom:10px; transition:all .2s;
}
.role-row:last-child { margin-bottom:0; }
.role-row:hover { border-color:var(--border-hi); background:var(--accent-glow); }
.role-emoji { font-size:20px; width:36px; text-align:center; flex-shrink:0; }
.role-name { font-size:14px; font-weight:700; color:var(--text-1); }
.role-sub  { font-size:11px; color:var(--text-3); margin-top:1px; }

/* ═══════════════════════════════════════════════
   STATS STRIP
═══════════════════════════════════════════════ */
.strip {
  background:var(--bg-2);
  border-top:1px solid var(--border); border-bottom:1px solid var(--border);
  padding:36px 40px;
}
.strip-inner {
  max-width:1200px; margin:0 auto;
  display:grid; grid-template-columns:repeat(5,1fr);
}
.strip-item {
  text-align:center; padding:0 16px;
  border-right:1px solid var(--stat-border);
}
.strip-item:last-child { border-right:none; }
.strip-num  { font-family:'Barlow Condensed',sans-serif; font-size:2.6rem; font-weight:800; color:var(--accent); line-height:1; }
.strip-label{ font-size:11px; color:var(--text-3); text-transform:uppercase; letter-spacing:.08em; margin-top:5px; }

/* ═══════════════════════════════════════════════
   SECTIONS
═══════════════════════════════════════════════ */
.sec { padding:110px 40px; }
.sec-inner { max-width:1200px; margin:0 auto; }
.tag {
  display:inline-block; padding:5px 14px; border-radius:999px;
  border:1px solid var(--border-hi); background:var(--accent-glow);
  font-size:11px; font-weight:700; color:var(--accent);
  letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px;
}
.sec-title {
  font-family:'Barlow Condensed',sans-serif;
  font-size:clamp(2.4rem,5vw,3.8rem); font-weight:800;
  line-height:.92; text-transform:uppercase; color:var(--text-1); margin-bottom:14px;
}
.sec-title em { color:var(--accent); font-style:normal; }
.sec-sub { font-size:16px; color:var(--text-2); line-height:1.7; max-width:540px; }

/* ═══════════════════════════════════════════════
   ABOUT — BENTO
═══════════════════════════════════════════════ */
.about-sec { background:var(--bg); }
.about-grid { display:grid; grid-template-columns:1fr 1fr; gap:70px; align-items:center; }
.bento {
  display:grid; grid-template-columns:1fr 1fr; grid-template-rows:260px 190px;
  gap:10px; border-radius:22px; overflow:hidden;
}
.bento-cell { overflow:hidden; }
.bento-cell:first-child { grid-row:span 2; }
.bento img {
  width:100%; height:100%; object-fit:cover;
  filter:brightness(.8) saturate(.75);
  transition:transform .5s ease,filter .35s;
}
.bento-cell:hover img { transform:scale(1.06); filter:brightness(.92) saturate(1.1); }

.check-list { list-style:none; margin:22px 0 32px; display:flex; flex-direction:column; gap:13px; }
.check-list li { display:flex; align-items:flex-start; gap:10px; font-size:15px; color:var(--text-2); }
.check-list li span { color:var(--accent); font-weight:800; flex-shrink:0; margin-top:1px; }

/* ═══════════════════════════════════════════════
   GALLERY STRIP
═══════════════════════════════════════════════ */
.gallery-sec { background:var(--bg); padding:0; }
.gallery-wrap { overflow:hidden; padding:60px 0; }
.gallery-label { text-align:center; margin-bottom:18px; }
.gallery-track {
  display:flex; gap:4px;
  animation:scroll 28s linear infinite;
  width:max-content;
}
.gallery-track:hover { animation-play-state:paused; }
.gallery-track img {
  width:300px; height:200px; object-fit:cover; flex-shrink:0;
  filter:brightness(.65) saturate(.65); border-radius:6px;
  transition:filter .3s,transform .3s;
}
.gallery-track img:hover { filter:brightness(.9) saturate(1.1); transform:scale(1.02); }
@keyframes scroll { from{transform:translateX(0)} to{transform:translateX(-50%)} }

/* ═══════════════════════════════════════════════
   FEATURES
═══════════════════════════════════════════════ */
.feat-sec { background:var(--bg-2); }
.feat-header { display:flex; justify-content:space-between; align-items:flex-end; gap:40px; margin-bottom:52px; flex-wrap:wrap; }
.feat-grid {
  display:grid; grid-template-columns:repeat(3,1fr);
  gap:1px; background:var(--border); border-radius:22px; overflow:hidden;
  box-shadow:var(--shadow-card);
}
.feat-card {
  background:var(--feat-bg); padding:30px 26px;
  position:relative; overflow:hidden; transition:background .2s;
}
.feat-card::after {
  content:''; position:absolute; top:0; left:0; right:0; height:2px;
  background:var(--accent); transform:scaleX(0); transform-origin:left; transition:transform .3s;
}
.feat-card:hover { background:var(--card-hover); }
.feat-card:hover::after { transform:scaleX(1); }
.feat-icon {
  width:50px; height:50px; border-radius:13px;
  background:var(--accent-glow); border:1px solid var(--border-hi);
  display:flex; align-items:center; justify-content:center;
  font-size:22px; margin-bottom:18px;
  transition:transform .25s;
}
.feat-card:hover .feat-icon { transform:scale(1.12) rotate(-4deg); }
.feat-title { font-family:'Barlow Condensed',sans-serif; font-size:1.2rem; font-weight:700; color:var(--text-1); text-transform:uppercase; letter-spacing:.04em; margin-bottom:9px; }
.feat-desc  { font-size:13px; color:var(--text-3); line-height:1.68; }

/* ═══════════════════════════════════════════════
   HOW IT WORKS
═══════════════════════════════════════════════ */
.how-sec { background:var(--bg); }
.steps { display:grid; grid-template-columns:repeat(4,1fr); gap:36px; margin-top:56px; position:relative; }
.steps::before {
  content:''; position:absolute; top:34px; left:12%; right:12%; height:1px;
  background:linear-gradient(90deg,transparent,var(--border-hi) 20%,var(--border-hi) 80%,transparent);
}
.step { text-align:center; position:relative; }
.step-n {
  width:68px; height:68px; border-radius:50%;
  background:var(--step-bg); border:2px solid var(--border-hi);
  margin:0 auto 18px;
  display:flex; align-items:center; justify-content:center;
  font-family:'Barlow Condensed',sans-serif; font-size:1.7rem; font-weight:800; color:var(--accent);
  position:relative; z-index:1; transition:all .3s;
}
.step:hover .step-n { background:var(--accent); color:var(--btn-dark-text); transform:scale(1.1); }
.step-title { font-weight:700; font-size:15px; color:var(--text-1); margin-bottom:7px; }
.step-desc  { font-size:13px; color:var(--text-3); line-height:1.6; }

/* ═══════════════════════════════════════════════
   ROLES
═══════════════════════════════════════════════ */
.roles-sec { background:var(--bg-2); }
.roles-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-top:56px; }
.role-card-big {
  border-radius:22px; overflow:hidden;
  border:1px solid var(--border); background:var(--card);
  transition:transform .3s,border-color .3s,box-shadow .3s;
}
.role-card-big:hover { transform:translateY(-10px); border-color:var(--accent); box-shadow:var(--shadow-xl); }
.role-photo { width:100%; height:200px; object-fit:cover; filter:brightness(.72) saturate(.75); transition:filter .4s; }
.role-card-big:hover .role-photo { filter:brightness(.9) saturate(1.1); }
.role-body { padding:24px; }
.role-badge {
  display:inline-flex; align-items:center; gap:6px;
  padding:4px 12px; border-radius:999px;
  background:var(--accent-glow); border:1px solid var(--border-hi);
  font-size:11px; font-weight:700; color:var(--accent);
  letter-spacing:.08em; text-transform:uppercase; margin-bottom:12px;
}
.role-h { font-family:'Barlow Condensed',sans-serif; font-size:1.55rem; font-weight:800; color:var(--text-1); text-transform:uppercase; margin-bottom:8px; }
.role-p { font-size:13px; color:var(--text-3); line-height:1.65; margin-bottom:16px; }
.role-perks { list-style:none; display:flex; flex-direction:column; gap:7px; }
.role-perks li { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-2); }
.role-perks li::before { content:'→'; color:var(--accent); font-weight:700; flex-shrink:0; }

/* ═══════════════════════════════════════════════
   TESTIMONIALS
═══════════════════════════════════════════════ */
.testi-sec { background:var(--bg); padding:110px 40px; }
.testi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:56px; }
.testi-card {
  background:var(--bg-3); border:1px solid var(--border);
  border-radius:20px; padding:28px;
  transition:border-color .25s,transform .3s;
}
.testi-card:hover { border-color:var(--border-hi); transform:translateY(-5px); }
.stars { color:var(--accent); font-size:14px; letter-spacing:3px; margin-bottom:14px; }
.quote { font-size:14px; color:var(--text-2); line-height:1.78; margin-bottom:22px; font-style:italic; }
.author { display:flex; align-items:center; gap:12px; }
.avatar {
  width:42px; height:42px; border-radius:50%; flex-shrink:0;
  background:var(--accent-glow); border:2px solid var(--border-hi);
  display:flex; align-items:center; justify-content:center;
  font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:700; color:var(--accent);
}
.auth-name { font-size:14px; font-weight:700; color:var(--text-1); }
.auth-role { font-size:12px; color:var(--text-3); margin-top:2px; }

/* ═══════════════════════════════════════════════
   FAQ
═══════════════════════════════════════════════ */
.faq-sec { background:var(--bg-2); }
.faq-wrap { max-width:700px; margin:56px auto 0; display:flex; flex-direction:column; gap:3px; }
.faq-item { border-radius:10px; overflow:hidden; }
.faq-q {
  display:flex; justify-content:space-between; align-items:center;
  padding:19px 22px;
  background:var(--bg-3); border:1px solid var(--border);
  font-size:15px; font-weight:600; color:var(--text-1);
  cursor:pointer; user-select:none; transition:background .2s;
}
.faq-q:hover { background:var(--accent-glow); }
.faq-icon {
  width:26px; height:26px; border-radius:50%; flex-shrink:0;
  background:var(--accent-glow); border:1px solid var(--border-hi);
  display:flex; align-items:center; justify-content:center;
  font-size:15px; color:var(--accent); transition:transform .3s;
}
.faq-item.open .faq-icon { transform:rotate(45deg); }
.faq-a {
  max-height:0; overflow:hidden; padding:0 22px;
  background:var(--card); border:1px solid var(--border); border-top:none;
  font-size:14px; color:var(--text-2); line-height:1.78;
  transition:max-height .4s ease,padding .35s;
}
.faq-item.open .faq-a { max-height:220px; padding:18px 22px; }

/* ═══════════════════════════════════════════════
   CTA BANNER
═══════════════════════════════════════════════ */
.cta-sec {
  position:relative; padding:120px 40px; text-align:center; overflow:hidden;
  background:
    linear-gradient(180deg,var(--bg) 0%,rgba(0,0,0,.5) 50%,var(--bg) 100%),
    url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=1800&q=80&fm=jpg') center/cover no-repeat;
}
[data-theme="light"] .cta-sec {
  background:
    linear-gradient(180deg,var(--bg) 0%,rgba(248,250,252,.88) 50%,var(--bg) 100%),
    url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=1800&q=80&fm=jpg') center/cover no-repeat;
}
.cta-sec::before {
  content:''; position:absolute; inset:0;
  background:radial-gradient(ellipse 55% 70% at 50% 80%,rgba(200,241,53,.08),transparent);
  pointer-events:none;
}
.cta-inner { position:relative; z-index:1; max-width:680px; margin:0 auto; }
.cta-h {
  font-family:'Barlow Condensed',sans-serif;
  font-size:clamp(2.6rem,6vw,4.8rem); font-weight:900;
  text-transform:uppercase; color:var(--text-1); line-height:.92; margin-bottom:18px;
}
.cta-h em { color:var(--accent); font-style:normal; }
.cta-p { font-size:16px; color:var(--text-2); line-height:1.65; margin-bottom:36px; }
.cta-btns { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }

/* ═══════════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════════ */
footer {
  background:var(--bg-2); border-top:1px solid var(--border);
  padding:60px 40px 36px;
}
.foot-grid {
  max-width:1200px; margin:0 auto;
  display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:48px;
  padding-bottom:40px; border-bottom:1px solid var(--border);
}
.foot-brand { font-family:'Barlow Condensed',sans-serif; font-size:1.8rem; font-weight:900; color:var(--text-1); margin-bottom:12px; }
.foot-brand em { color:var(--accent); font-style:normal; }
.foot-tagline { font-size:13px; color:var(--text-3); line-height:1.7; max-width:230px; }
.foot-col-title { font-size:11px; font-weight:700; color:var(--accent); letter-spacing:.12em; text-transform:uppercase; margin-bottom:16px; }
.foot-links { list-style:none; display:flex; flex-direction:column; gap:10px; }
.foot-links a { font-size:13px; color:var(--text-3); transition:color .2s; }
.foot-links a:hover { color:var(--text-1); }
.foot-bottom {
  max-width:1200px; margin:28px auto 0;
  display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;
  font-size:12px; color:var(--text-3);
}
.foot-bottom span { color:var(--accent); }

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media(max-width:1060px){
  .hero-inner    { grid-template-columns:1fr; }
  .hero-panel    { max-width:480px; }
  .about-grid    { grid-template-columns:1fr; }
  .bento         { max-width:580px; }
  .feat-grid     { grid-template-columns:1fr 1fr; }
  .roles-grid    { grid-template-columns:1fr; max-width:440px; margin-inline:auto; }
  .testi-grid    { grid-template-columns:1fr 1fr; }
  .steps         { grid-template-columns:1fr 1fr; }
  .steps::before { display:none; }
  .strip-inner   { grid-template-columns:repeat(3,1fr); }
  .strip-item:nth-child(3){ border-right:none; }
  .foot-grid     { grid-template-columns:1fr 1fr; gap:32px; }
}
@media(max-width:768px){
  .nav-links,.nav-right { display:none; }
  .mob-menu { display:flex; }
  .mob-theme { display:flex !important; }
  .nav { padding:0 20px; }
  .hero { padding:100px 20px 60px; }
  .sec { padding:72px 20px; }
  .testi-sec,.cta-sec { padding:72px 20px; }
  footer { padding:48px 20px 28px; }
  .strip { padding:28px 20px; }
  .strip-inner   { grid-template-columns:1fr 1fr; }
  .strip-item:nth-child(3){ border-right:none; }
  .strip-item:nth-child(n+4){ border-top:1px solid var(--stat-border); padding-top:20px; margin-top:4px; }
  .feat-grid     { grid-template-columns:1fr; }
  .testi-grid    { grid-template-columns:1fr; }
  .hero-btns     { flex-direction:column; }
  .btn-lime,.btn-ghost { justify-content:center; text-align:center; }
  .bento { grid-template-columns:1fr; grid-template-rows:200px 155px 155px; }
  .bento-cell:first-child { grid-row:1; }
  .foot-grid { grid-template-columns:1fr; gap:28px; }
  .foot-bottom { flex-direction:column; text-align:center; }
  .steps { grid-template-columns:1fr; }
  .cta-btns { flex-direction:column; align-items:center; }
  .cta-btns .btn-lime,.cta-btns .btn-ghost { width:100%; max-width:320px; }
  .roles-grid { max-width:100%; }
}
@media(max-width:480px){
  .hero-counters { gap:20px; }
  .strip-inner   { grid-template-columns:1fr; }
  .strip-item    { border-right:none !important; border-bottom:1px solid var(--stat-border); padding:14px 0; }
  .strip-item:last-child { border-bottom:none; }
}
</style>
</head>
<body>

<!-- ════ NAVBAR ════ -->
<nav class="nav" id="nav">
  <a href="index.php" class="nav-logo">🏋️ GYM<em>PRO</em></a>

  <div class="nav-links">
    <a href="#about">About</a>
    <a href="#features">Features</a>
    <a href="#roles">Roles</a>
    <a href="#faq">FAQ</a>
  </div>

  <div class="nav-right">
    <button class="theme-btn" id="themeBtn" title="Toggle theme" aria-label="Toggle dark/light mode">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <a href="login.php"    class="nav-login">Login</a>
    <a href="register.php" class="nav-cta">Get Started →</a>
  </div>

  <div class="mob-menu">
    <!-- Mobile theme toggle -->
    <button class="theme-btn mob-theme" id="themeBtnMob" title="Toggle theme" style="display:none;" aria-label="Toggle theme">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <button class="hamburger" id="ham" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<div class="mob-nav" id="mobNav">
  <a href="#about"    onclick="closeMob()">About</a>
  <a href="#features" onclick="closeMob()">Features</a>
  <a href="#roles"    onclick="closeMob()">Roles</a>
  <a href="#faq"      onclick="closeMob()">FAQ</a>
  <div class="mob-actions">
    <a href="login.php"    class="btn-ghost">Login</a>
    <a href="register.php" class="btn-lime">Register →</a>
  </div>
</div>

<!-- ════ HERO ════ -->
<section class="hero">
  <div class="orb o1"></div>
  <div class="orb o2"></div>
  <div class="hero-photo" id="heroBg"></div>

  <div class="hero-inner">
    <div>
      <div class="eyebrow"><span class="dot"></span>Smart Gym Management Platform</div>
      <h1 class="hero-h1">
        TRAIN.<br>
        <span class="lime">MANAGE.</span>
        <span class="ghost">GROW.</span>
      </h1>
      <p class="hero-p">
        GymPro unifies member management, trainer assignments, slot booking,
        payments, complaints and BMI tracking into one clean, powerful platform.
        Built for admins, trainers and members.
      </p>
      <div class="hero-btns">
        <a href="register.php" class="btn-lime">Start Free →</a>
        <a href="login.php"    class="btn-ghost">Login to Dashboard</a>
      </div>
      <div class="hero-counters">
        <div class="hc"><div class="hc-num" data-count="3">0</div><div class="hc-label">User Roles</div></div>
        <div class="hc"><div class="hc-num" data-count="12">0</div><div class="hc-label">Modules</div></div>
        <div class="hc"><div class="hc-num" data-count="100">0</div><div class="hc-label">% Secure</div></div>
        <div class="hc"><div class="hc-num" data-count="5">0</div><div class="hc-label">Min OTP</div></div>
      </div>
    </div>

    <div>
      <div class="hero-panel">
        <img src="https://images.unsplash.com/photo-1540497077202-7c8a3999166f?w=640&q=80&fm=jpg" alt="Gym interior" loading="lazy">
        <div class="panel-body">
          <div class="panel-eyebrow">Role-Based Access Control</div>
          <div class="role-row"><div class="role-emoji">👑</div><div><div class="role-name">Admin</div><div class="role-sub">Full control — users, payments, reports</div></div></div>
          <div class="role-row"><div class="role-emoji">🧑‍🏫</div><div><div class="role-name">Trainer</div><div class="role-sub">Assign diet & exercise plans, leaves</div></div></div>
          <div class="role-row"><div class="role-emoji">💪</div><div><div class="role-name">Member</div><div class="role-sub">Book slots, check BMI, view plans</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ════ STATS STRIP ════ -->
<div class="strip">
  <div class="strip-inner">
    <div class="strip-item"><div class="strip-num">∞</div><div class="strip-label">Members Supported</div></div>
    <div class="strip-item"><div class="strip-num">100%</div><div class="strip-label">Role Security</div></div>
    <div class="strip-item"><div class="strip-num">PHP</div><div class="strip-label">Powered Backend</div></div>
    <div class="strip-item"><div class="strip-num">MySQL</div><div class="strip-label">Database</div></div>
    <div class="strip-item"><div class="strip-num">AWS</div><div class="strip-label">Cloud Ready</div></div>
  </div>
</div>

<!-- ════ ABOUT ════ -->
<section class="sec about-sec" id="about">
  <div class="sec-inner">
    <div class="about-grid">
      <div class="bento reveal">
        <div class="bento-cell"><img src="https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=600&q=80&fm=jpg" alt="Training" loading="lazy"></div>
        <div class="bento-cell"><img src="https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=400&q=80&fm=jpg" alt="Weights" loading="lazy"></div>
        <div class="bento-cell"><img src="https://images.unsplash.com/photo-1549476464-37392f717541?w=400&q=80&fm=jpg" alt="Cardio"  loading="lazy"></div>
      </div>
      <div class="reveal d2">
        <div class="tag">About GymPro</div>
        <h2 class="sec-title">BUILT FOR <em>REAL</em> GYMS</h2>
        <p class="sec-sub">A full-stack gym management system designed to eliminate paperwork and streamline every operation — from member onboarding to payment approvals.</p>
        <ul class="check-list">
          <li><span>✓</span>One-per-day slot booking with capacity enforcement (max 20)</li>
          <li><span>✓</span>Trainer-assigned diet and exercise plans per member</li>
          <li><span>✓</span>OTP-based secure email password recovery via PHPMailer</li>
          <li><span>✓</span>Admin payment approval with automatic membership activation</li>
          <li><span>✓</span>Full complaint submission and admin reply system</li>
          <li><span>✓</span>BMI calculator with personalised health tips for members</li>
        </ul>
        <a href="register.php" class="btn-lime" style="display:inline-flex;">Join Now →</a>
      </div>
    </div>
  </div>
</section>

<!-- ════ GALLERY ════ -->
<div class="gallery-sec">
  <div class="gallery-wrap">
    <div class="gallery-label"><div class="tag">Our Facilities</div></div>
    <div style="overflow:hidden;">
      <div class="gallery-track">
        <?php
        $imgs = [
          'photo-1534438327276-14e5300c3a48','photo-1571902943202-507ec2618e8f',
          'photo-1483721310020-03333e577078','photo-1506126613408-eca07ce68773',
          'photo-1526506118085-60ce8714f8c5','photo-1558618666-fcd25c85cd64',
          'photo-1540497077202-7c8a3999166f','photo-1579758629938-03607ccdbaba',
        ];
        foreach(array_merge($imgs,$imgs) as $id) {
          echo "<img src=\"https://images.unsplash.com/{$id}?w=400&q=70&fm=jpg\" alt=\"Gym\" loading=\"lazy\">";
        }
        ?>
      </div>
    </div>
  </div>
</div>

<!-- ════ FEATURES ════ -->
<section class="sec feat-sec" id="features">
  <div class="sec-inner">
    <div class="feat-header">
      <div class="reveal">
        <div class="tag">Platform Features</div>
        <h2 class="sec-title">EVERYTHING <em>YOUR GYM</em> NEEDS</h2>
      </div>
      <p class="sec-sub reveal d2" style="max-width:320px;">A complete toolkit for running a professional fitness facility — no extra software required.</p>
    </div>
    <div class="feat-grid">
      <?php
      $feats = [
        ['👑','Admin Dashboard','Full control over users, payments, complaints, time slots and member management from a single panel.'],
        ['🧑‍🏫','Trainer Portal','Assign custom diet and exercise plans, manage leave requests, and view your member list in one place.'],
        ['📅','Smart Slot Booking','One booking per day per member. Crowd-control capacity (max 20) enforced automatically by the system.'],
        ['💳','Payment Management','Members submit payments, admins approve or reject. Membership dates update automatically on approval.'],
        ['💬','Complaint System','Members submit issues, admins reply and update status. Complete conversation history maintained.'],
        ['🔐','OTP Password Reset','Secure 6-digit OTP sent via PHPMailer with 5-minute expiry. Visual countdown timer included.'],
        ['⚖️','BMI Calculator','Built into the member dashboard. Metric + imperial with personalised health recommendations.'],
        ['🗓️','Trainer Leave System','Trainers apply for leave with date ranges. Admins review, approve or reject from their dashboard.'],
        ['☁️','Cloud Deployable','Runs on XAMPP locally or AWS EC2 with Apache in production. MySQL database, PHP backend.'],
      ];
      foreach($feats as $i=>$f) {
        $d = $i%3===0?'':($i%3===1?' d1':' d2');
        echo "<div class=\"feat-card reveal{$d}\"><div class=\"feat-icon\">{$f[0]}</div><div class=\"feat-title\">{$f[1]}</div><p class=\"feat-desc\">{$f[2]}</p></div>";
      }
      ?>
    </div>
  </div>
</section>

<!-- ════ HOW IT WORKS ════ -->
<section class="sec how-sec">
  <div class="sec-inner">
    <div style="text-align:center;" class="reveal">
      <div class="tag">How It Works</div>
      <h2 class="sec-title">UP AND RUNNING IN <em>4 STEPS</em></h2>
      <p class="sec-sub" style="margin:0 auto;">From registration to full gym management in minutes.</p>
    </div>
    <div class="steps">
      <div class="step reveal d1"><div class="step-n">01</div><div class="step-title">Register / Login</div><p class="step-desc">Members self-register. Admin and trainer accounts are created by the super admin.</p></div>
      <div class="step reveal d2"><div class="step-n">02</div><div class="step-title">Admin Configures</div><p class="step-desc">Admin adds trainers, creates time slots with capacity, and configures package prices.</p></div>
      <div class="step reveal d3"><div class="step-n">03</div><div class="step-title">Book & Pay</div><p class="step-desc">Members book slots and submit membership payments for admin approval.</p></div>
      <div class="step reveal d4"><div class="step-n">04</div><div class="step-title">Train & Track</div><p class="step-desc">Trainers assign plans. Members check BMI, view diet & exercise, track progress.</p></div>
    </div>
  </div>
</section>

<!-- ════ ROLES ════ -->
<section class="sec roles-sec" id="roles">
  <div class="sec-inner">
    <div style="text-align:center;" class="reveal">
      <div class="tag">Role Access</div>
      <h2 class="sec-title">ONE PLATFORM, <em>THREE ROLES</em></h2>
      <p class="sec-sub" style="margin:0 auto;">Each role has its own dedicated dashboard with exactly the features they need — nothing more, nothing less.</p>
    </div>
    <div class="roles-grid">
      <div class="role-card-big reveal d1">
        <img src="https://images.unsplash.com/photo-1553484771-371a605b060b?w=640&q=80&fm=jpg" alt="Admin" class="role-photo" loading="lazy">
        <div class="role-body">
          <div class="role-badge">👑 Admin</div>
          <div class="role-h">Full Control</div>
          <p class="role-p">The admin has complete visibility and control over every aspect of the gym management system.</p>
          <ul class="role-perks">
            <li>Manage members, trainers & admins</li>
            <li>Approve & reject payments</li>
            <li>Set up time slots & capacity</li>
            <li>Reply to member complaints</li>
            <li>Approve trainer leave requests</li>
            <li>View today's bookings & history</li>
          </ul>
        </div>
      </div>
      <div class="role-card-big reveal d2">
        <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=640&q=80&fm=jpg" alt="Trainer" class="role-photo" loading="lazy">
        <div class="role-body">
          <div class="role-badge">🧑‍🏫 Trainer</div>
          <div class="role-h">Plan & Guide</div>
          <p class="role-p">Trainers manage their members' fitness journeys with custom plans and personal scheduling.</p>
          <ul class="role-perks">
            <li>Assign diet plans to members</li>
            <li>Create custom exercise programs</li>
            <li>Edit & delete past assignments</li>
            <li>Submit leave applications</li>
            <li>View your weekly time slots</li>
            <li>Track plan status updates</li>
          </ul>
        </div>
      </div>
      <div class="role-card-big reveal d3">
        <img src="https://images.unsplash.com/photo-1599058945522-28d584b6f0ff?w=640&q=80&fm=jpg" alt="Member" class="role-photo" loading="lazy">
        <div class="role-body">
          <div class="role-badge">💪 Member</div>
          <div class="role-h">Train & Track</div>
          <p class="role-p">Members have everything they need to stay committed and track their fitness journey.</p>
          <ul class="role-perks">
            <li>Book gym slots by day & trainer</li>
            <li>View assigned diet & exercise plans</li>
            <li>Calculate BMI with recommendations</li>
            <li>Submit and track complaints</li>
            <li>Pay and manage membership</li>
            <li>Cancel bookings when needed</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ════ TESTIMONIALS ════ -->
<section class="testi-sec">
  <div class="sec-inner">
    <div style="text-align:center;" class="reveal">
      <div class="tag">Testimonials</div>
      <h2 class="sec-title">WHAT USERS <em>SAY</em></h2>
    </div>
    <div class="testi-grid">
      <div class="testi-card reveal d1">
        <div class="stars">★★★★★</div>
        <p class="quote">"Managing my gym used to be a nightmare of spreadsheets. GymPro turned it into a 5-minute daily task. Payment tracking alone saved me hours every week."</p>
        <div class="author"><div class="avatar">RK</div><div><div class="auth-name">Rahul Kumar</div><div class="auth-role">Gym Owner · Admin</div></div></div>
      </div>
      <div class="testi-card reveal d2">
        <div class="stars">★★★★★</div>
        <p class="quote">"The diet and exercise plan assignment feature is exactly what I needed. I can now send personalised plans to 30 members in minutes instead of one hour."</p>
        <div class="author"><div class="avatar">PS</div><div><div class="auth-name">Priya Sharma</div><div class="auth-role">Certified Trainer</div></div></div>
      </div>
      <div class="testi-card reveal d3">
        <div class="stars">★★★★★</div>
        <p class="quote">"The BMI calculator and booking system are brilliant. I can see my diet plan, check my BMI and book my slot all in one place. Super clean interface!"</p>
        <div class="author"><div class="avatar">AJ</div><div><div class="auth-name">Arjun Joshi</div><div class="auth-role">Member · 6 months</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- ════ FAQ ════ -->
<section class="sec faq-sec" id="faq">
  <div class="sec-inner">
    <div style="text-align:center;" class="reveal">
      <div class="tag">FAQ</div>
      <h2 class="sec-title">COMMON <em>QUESTIONS</em></h2>
      <p class="sec-sub" style="margin:0 auto;">Everything you need to know before getting started.</p>
    </div>
    <div class="faq-wrap">
      <?php
      $faqs = [
        ['Who can use GymPro?', 'GymPro supports three roles — Admin, Trainer and Member — each with a separate dedicated dashboard. An admin creates trainer and admin accounts; members can self-register.'],
        ['How are memberships handled?', 'Members submit a payment request choosing a plan (1, 3, 6 or 12 months). The admin approves or rejects it. On approval, the membership dates are automatically calculated and activated.'],
        ['Can a member book multiple slots per day?', 'No. The system enforces exactly one active booking per day per member. This prevents overcrowding and ensures fair access for all members.'],
        ['How does the OTP password reset work?', 'The user enters their registered email. A 6-digit OTP is sent via PHPMailer (Gmail SMTP). The OTP expires automatically after 5 minutes. A visual countdown timer is shown on the verification page.'],
        ['What tech stack does GymPro use?', 'PHP 8.x backend, MySQL/MySQLi database, PHPMailer for emails, Apache web server. Deployable on XAMPP locally or AWS EC2 in production. No frameworks — plain PHP and vanilla JS.'],
        ['Is there a mobile-friendly interface?', 'Yes! The entire system — landing page, dashboards, and all management pages — is fully responsive and works seamlessly on mobile phones and tablets.'],
      ];
      foreach($faqs as $f) {
        echo "<div class=\"faq-item\"><div class=\"faq-q\" onclick=\"toggleFaq(this)\">{$f[0]}<span class=\"faq-icon\">+</span></div><div class=\"faq-a\">{$f[1]}</div></div>";
      }
      ?>
    </div>
  </div>
</section>

<!-- ════ CTA ════ -->
<div class="cta-sec">
  <div class="cta-inner reveal">
    <h2 class="cta-h">READY TO RUN A<br><em>SMARTER GYM?</em></h2>
    <p class="cta-p">Join GymPro today. Set up your gym, add your trainers and start managing members in minutes.</p>
    <div class="cta-btns">
      <a href="register.php" class="btn-lime">Create Free Account →</a>
      <a href="login.php"    class="btn-ghost">Login to Dashboard</a>
    </div>
  </div>
</div>

<!-- ════ FOOTER ════ -->
<footer>
  <div class="foot-grid">
    <div>
      <div class="foot-brand">🏋️ GYM<em>PRO</em></div>
      <p class="foot-tagline">A complete gym management platform built on PHP, MySQL and deployed on AWS. Built for real gyms that need real results.</p>
    </div>
    <div>
      <div class="foot-col-title">Navigation</div>
      <ul class="foot-links">
        <li><a href="#about">About</a></li>
        <li><a href="#features">Features</a></li>
        <li><a href="#roles">Roles</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>
    <div>
      <div class="foot-col-title">Account</div>
      <ul class="foot-links">
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
        <li><a href="forgot_password.php">Reset Password</a></li>
      </ul>
    </div>
    <div>
      <div class="foot-col-title">Tech Stack</div>
      <ul class="foot-links">
        <li><a href="#">PHP 8.x</a></li>
        <li><a href="#">MySQL / MySQLi</a></li>
        <li><a href="#">PHPMailer · Gmail</a></li>
        <li><a href="#">Apache / XAMPP</a></li>
        <li><a href="#">AWS EC2</a></li>
      </ul>
    </div>
  </div>
  <div class="foot-bottom">
    <span>© <?php echo date('Y'); ?> <span>GymPro</span> · All rights reserved.</span>
    <span>Built with PHP, MySQL &amp; AWS · Iron Forge Design System</span>
  </div>
</footer>

<script>
/* ── Theme ── */
const html = document.documentElement;
const THEME_KEY = 'gymproTheme';

function applyTheme(t) {
  html.setAttribute('data-theme', t);
  localStorage.setItem(THEME_KEY, t);
}

// Load saved theme
const saved = localStorage.getItem(THEME_KEY);
if (saved) applyTheme(saved);

function toggleTheme() {
  const cur = html.getAttribute('data-theme');
  applyTheme(cur === 'dark' ? 'light' : 'dark');
}
document.getElementById('themeBtn').addEventListener('click', toggleTheme);
document.getElementById('themeBtnMob').addEventListener('click', toggleTheme);

/* ── Navbar scroll ── */
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('stuck', window.scrollY > 50);
}, { passive: true });

/* ── Hamburger ── */
const ham    = document.getElementById('ham');
const mobNav = document.getElementById('mobNav');
ham.addEventListener('click', () => {
  const o = ham.classList.toggle('open');
  mobNav.classList.toggle('open', o);
});
function closeMob() { ham.classList.remove('open'); mobNav.classList.remove('open'); }

/* ── Smooth anchor scroll ── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); closeMob(); }
  });
});

/* ── Scroll reveal ── */
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));

/* ── Counter animation ── */
function countUp(el) {
  const target = +el.dataset.count;
  let start = null;
  const step = ts => {
    if (!start) start = ts;
    const p = Math.min((ts - start) / 1300, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(ease * target);
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}
const cObs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { countUp(e.target); cObs.unobserve(e.target); } });
}, { threshold: 0.6 });
document.querySelectorAll('[data-count]').forEach(el => cObs.observe(el));

/* ── FAQ accordion ── */
function toggleFaq(q) {
  const item = q.parentElement;
  document.querySelectorAll('.faq-item').forEach(i => { if (i !== item) i.classList.remove('open'); });
  item.classList.toggle('open');
}

/* ── Hero parallax ── */
const heroBg = document.getElementById('heroBg');
window.addEventListener('scroll', () => {
  if (heroBg && window.scrollY < window.innerHeight) {
    heroBg.style.transform = `translateY(${window.scrollY * 0.22}px)`;
  }
}, { passive: true });
</script>
</body>
</html>