<template>
  <svg viewBox="-10 -10 260 180" role="img" aria-labelledby="autofix-logo-title autofix-logo-description">
    <title id="autofix-logo-title">Logo de AUTOFIX</title>
    <desc id="autofix-logo-description">Escudo con una letra A y conexiones electrónicas</desc>
    <defs>
      <linearGradient id="autofix-shield" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="var(--ui-color-primary-500, #10b981)" />
        <stop offset="1" stop-color="var(--ui-color-primary-700, #047857)" />
      </linearGradient>
      <filter id="autofix-green-glow" x="-80%" y="-80%" width="260%" height="260%">
        <feGaussianBlur stdDeviation="3" result="blur" />
        <feMerge>
          <feMergeNode in="blur" />
          <feMergeNode in="SourceGraphic" />
        </feMerge>
      </filter>
      <clipPath id="autofix-a-body" clipPathUnits="userSpaceOnUse">
        <path d="M102 36h36l20 82h-21l-5-22h-24l-5 22H82zm10 42h16l-8-35z" fill-rule="evenodd" clip-rule="evenodd" />
      </clipPath>
    </defs>

    <g fill="none" stroke="var(--ui-color-primary-600, #059669)" stroke-linecap="round" stroke-linejoin="round" stroke-width="7">
      <path d="M20 59h31l16 18h18M8 80h46l12-11h19M21 101h31l18-19h15" />
      <path d="M220 59h-31l-16 18h-18M232 80h-46l-12-11h-19M219 101h-31l-18-19h-15" />
      <circle cx="14" cy="59" r="8" fill="#f8fafc" />
      <circle cx="8" cy="80" r="8" fill="#f8fafc" />
      <circle cx="16" cy="101" r="8" fill="#f8fafc" />
      <circle cx="226" cy="59" r="8" fill="#f8fafc" />
      <circle cx="232" cy="80" r="8" fill="#f8fafc" />
      <circle cx="224" cy="101" r="8" fill="#f8fafc" />
    </g>
    <g fill="var(--ui-color-primary-300, #6ee7b7)">
      <circle class="node-light" cx="14" cy="59" r="8" />
      <circle class="node-light" cx="8" cy="80" r="8" />
      <circle class="node-light" cx="16" cy="101" r="8" />
      <circle class="node-light" cx="226" cy="59" r="8" />
      <circle class="node-light" cx="232" cy="80" r="8" />
      <circle class="node-light" cx="224" cy="101" r="8" />
    </g>
    <g fill="none" stroke="var(--ui-color-primary-300, #6ee7b7)" stroke-linecap="round" stroke-linejoin="round" stroke-width="4">
      <path class="circuit-light" pathLength="100" d="M20 59h31l16 18h18" />
      <path class="circuit-light" pathLength="100" d="M8 80h46l12-11h19" />
      <path class="circuit-light" pathLength="100" d="M21 101h31l18-19h15" />
      <path class="circuit-light" pathLength="100" d="M220 59h-31l-16 18h-18" />
      <path class="circuit-light" pathLength="100" d="M232 80h-46l-12-11h-19" />
      <path class="circuit-light" pathLength="100" d="M219 101h-31l-18-19h-15" />
    </g>

    <path d="M120 8 174 28v51c0 35-21 59-54 73-33-14-54-38-54-73V28z" fill="url(#autofix-shield)" stroke="var(--ui-color-primary-800, #065f46)" stroke-width="3" />
    <path d="M102 36h36l20 82h-21l-5-22h-24l-5 22H82zm10 42h16l-8-35z" fill="#111827" fill-rule="evenodd" />
    <g clip-path="url(#autofix-a-body)" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="3">
      <path d="m99 47 13 8-17 10 21 10-26 15 16 8M141 47l-13 8 17 10-21 10 26 15-16 8" />
      <path d="M120 38v40" />
    </g>
    <path d="M102 36h36l20 82h-21l-5-22h-24l-5 22H82zm10 42h16l-8-35z" fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="5" />
    <g fill="none" stroke="var(--ui-color-primary-300, #6ee7b7)" stroke-linecap="round" stroke-linejoin="round" stroke-width="4">
      <path class="a-light" pathLength="100" d="M120 36h18l20 82h-21l-5-22h-12" />
      <path class="a-light" pathLength="100" d="M120 36h-18l-20 82h21l5-22h12" />
      <path class="a-light" pathLength="100" d="M112 78h16l-8-35z" />
    </g>
    <g clip-path="url(#autofix-a-body)" fill="none" stroke="var(--ui-color-primary-300, #6ee7b7)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5">
      <path class="a-light" pathLength="100" d="m99 47 13 8-17 10 21 10-26 15 16 8" />
      <path class="a-light" pathLength="100" d="m141 47-13 8 17 10-21 10 26 15-16 8" />
      <path class="a-light" pathLength="100" d="M120 38v40" />
    </g>
    <circle class="collision-light" cx="120" cy="96" r="5" fill="var(--ui-color-primary-200, #a7f3d0)" />
  </svg>
</template>

<style scoped>
.circuit-light {
  animation: circuit-flow 4s linear infinite;
  filter: url(#autofix-green-glow);
  opacity: 0;
  stroke-dasharray: 16 110;
  stroke-dashoffset: 0;
}
.a-light {
  animation: a-flow 4s ease-in-out infinite;
  filter: url(#autofix-green-glow);
  opacity: 0;
  stroke-dasharray: 14 110;
  stroke-dashoffset: 0;
}
.node-light,
.collision-light {
  filter: url(#autofix-green-glow);
  opacity: 0;
  transform-box: fill-box;
  transform-origin: center;
}
.node-light { animation: node-pulse 4s ease-out infinite; }
.collision-light { animation: collision-pulse 4s ease-out infinite; }
@keyframes circuit-flow {
  0% { opacity: 0; stroke-dashoffset: 0; }
  2% { opacity: 1; }
  48% { opacity: 1; stroke-dashoffset: -100; }
  53%, 100% { opacity: 0; stroke-dashoffset: -100; }
}
@keyframes node-pulse {
  0% { opacity: 0; transform: scale(.9); }
  2% { opacity: 1; transform: scale(1.18); }
  8% { opacity: .92; transform: scale(1.08); }
  48% { opacity: .08; transform: scale(1); }
  55%, 100% { opacity: 0; transform: scale(.95); }
}
@keyframes a-flow {
  0%, 45% { opacity: 0; stroke-dashoffset: 0; }
  49% { opacity: 1; stroke-dashoffset: 0; }
  84% { opacity: 1; stroke-dashoffset: -100; }
  90%, 100% { opacity: 0; stroke-dashoffset: -100; }
}
@keyframes collision-pulse {
  0%, 81% { opacity: 0; transform: scale(.4); }
  85% { opacity: 1; transform: scale(1.5); }
  92% { opacity: .25; transform: scale(1); }
  96%, 100% { opacity: 0; transform: scale(.7); }
}
@media (prefers-reduced-motion: reduce) {
  .circuit-light,
  .a-light,
  .node-light,
  .collision-light { animation: none; }
}
</style>
