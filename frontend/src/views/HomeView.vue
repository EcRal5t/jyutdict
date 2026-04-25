<script setup>
import { useRouter } from 'vue-router'
import { ref, onMounted, onUnmounted } from 'vue'

const router = useRouter()
const inputChara = ref('')

const goToDetail = () => {
  const char = inputChara.value.trim();
  if (char) {
    router.push({ path: '/detail', query: { chara: char } })
  }
}

// Background Elements Generation
const shards = ref([])
// Canvas for particles
const particleCanvas = ref(null)
let animationId = null

const colors = [
  '#D32913', // Brand Red
  '#FFD700', // Gold/Yellow
  '#2196F3', // Blue
  '#4CAF50', // Green
  '#9C27B0', // Purple
  '#009688', // Teal
  '#FF5722', // Deep Orange
]

const randomRange = (min, max) => Math.random() * (max - min) + min

const isInCenter = (top, left) => {
  // Avoid central area: Top 35%-65%, Left 20%-80%
  return (top > 35 && top < 65) && (left > 20 && left < 80)
}

const generateElements = () => {
  // Generate Shards (Background Shapes)
  const shardCount = 18
  shards.value = Array.from({ length: shardCount }, (_, i) => {
    // Generate irregular triangle points
    const p1 = `${randomRange(0, 40)}% ${randomRange(0, 40)}%`
    const p2 = `${randomRange(60, 100)}% ${randomRange(0, 40)}%`
    const p3 = `${randomRange(40, 60)}% ${randomRange(60, 100)}%`

    let top, left
    let attempts = 0
    // Rejection sampling to avoid center
    do {
      top = randomRange(-10, 110)
      left = randomRange(-10, 110)
      attempts++
    } while (isInCenter(top, left) && attempts < 10)

    const size = randomRange(150, 400);

    return {
      id: i,
      wrapperStyle: {
        top: `${top}%`,
        left: `${left}%`,
        width: `${size}px`,
        height: `${size}px`,
        // Move animation to wrapper
        animationName: i % 2 === 0 ? 'bg-float' : 'bg-float-reverse',
        animationDuration: `${randomRange(15, 45)}s`,
        animationDelay: `${randomRange(-40, 0)}s`,
        animationTimingFunction: 'ease-in-out',
        animationIterationCount: 'infinite',
        animationDirection: 'alternate',
        zIndex: 0
      },
      innerStyle: {
        width: '100%',
        height: '100%',
        backgroundColor: colors[Math.floor(Math.random() * colors.length)],
        opacity: randomRange(0.04, 0.09),
        filter: `blur(${randomRange(40, 90)}px)`,
        // Static rotation here, so browser rasterizes it once
        transform: `rotate(${randomRange(0, 360)}deg)`,
        clipPath: `polygon(${p1}, ${p2}, ${p3})`,
      }
    }
  })
}

// Particle System
class Particle {
  constructor(canvasWidth, canvasHeight) {
    this.canvasWidth = canvasWidth
    this.canvasHeight = canvasHeight
    this.reset(true)
  }

  reset(initial = false) {
    this.x = Math.random() * this.canvasWidth
    // If initial, anywhere. If reset, start at bottom
    this.y = initial ? Math.random() * this.canvasHeight : this.canvasHeight + 10

    // Slight horizontal drift
    this.vx = randomRange(-0.05, 0.05)
    // Upward float
    this.vy = randomRange(-0.02, -0.08)

    this.size = randomRange(2, 5) // slightly larger to account for "blur" gradient
    this.color = colors[Math.floor(Math.random() * colors.length)]

    this.opacity = 0
    this.fadeIn = true
    this.maxOpacity = randomRange(0.1, 0.3)
    this.fadeSpeed = randomRange(0.005, 0.01)
  }

  update() {
    this.x += this.vx
    this.y += this.vy

    // Fade in/out logic
    if (this.fadeIn) {
      this.opacity += this.fadeSpeed
      if (this.opacity >= this.maxOpacity) {
        this.fadeIn = false
      }
    } else {
      // Start fading out when getting near top or if it's been alive long
      // Simplification: random fade out or fade out near top
      if (this.y < this.canvasHeight * 0.2) {
        this.opacity -= this.fadeSpeed
      }
    }

    // Reset if out of bounds or invisible
    if (this.y < -10 || (this.opacity <= 0 && !this.fadeIn)) {
      this.reset()
    }
  }

  draw(ctx) {
    if (this.opacity <= 0) return

    ctx.globalAlpha = this.opacity
    ctx.beginPath()
    // Use radial gradient to simulate blur efficiently
    // x, y, innerRadius, x, y, outerRadius
    const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.size)
    gradient.addColorStop(0, this.color)
    gradient.addColorStop(1, 'transparent')

    ctx.fillStyle = gradient
    // Draw a larger circle to contain the gradient
    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2)
    ctx.fill()
    ctx.globalAlpha = 1.0
  }
}

const particles = []

const initParticles = () => {
  const canvas = particleCanvas.value
  if (!canvas) return

  const ctx = canvas.getContext('2d')

  // High DPI support
  const dpr = window.devicePixelRatio || 1
  const rect = canvas.getBoundingClientRect()

  canvas.width = rect.width * dpr
  canvas.height = rect.height * dpr
  ctx.scale(dpr, dpr)

  const width = rect.width
  const height = rect.height

  // Recreate particles on resize
  particles.length = 0
  const particleCount = 25 // Can easily support more now
  for (let i = 0; i < particleCount; i++) {
    particles.push(new Particle(width, height))
  }

  const animate = () => {
    ctx.clearRect(0, 0, width, height)

    particles.forEach(p => {
      p.update()
      p.draw(ctx)
    })

    animationId = requestAnimationFrame(animate)
  }

  if (animationId) cancelAnimationFrame(animationId)
  animate()
}

// Handle Resize
let resizeTimeout
const onResize = () => {
  clearTimeout(resizeTimeout)
  resizeTimeout = setTimeout(initParticles, 200)
}

onMounted(() => {
  generateElements()
  initParticles()
  window.addEventListener('resize', onResize)
})

onUnmounted(() => {
  if (animationId) cancelAnimationFrame(animationId)
  window.removeEventListener('resize', onResize)
})
</script>

<template>
  <div
    class="relative w-full flex-1 flex flex-col items-center justify-center overflow-hidden transition-colors duration-300">

    <!-- Dynamic Background Layer -->
    <!-- Fixed position to cover entire screen including footer, but behind content -->
    <div
      class="fixed inset-0 overflow-hidden pointer-events-none select-none z-0 bg-[#F4F4EE] dark:bg-slate-900 transition-colors duration-300">

      <!-- Shards -->
      <div v-for="shard in shards" :key="`shard-${shard.id}`" class="absolute will-change-transform"
        :style="shard.wrapperStyle">
        <div class="w-full h-full" :style="shard.innerStyle"></div>
      </div>

      <!-- Particles Canvas -->
      <canvas ref="particleCanvas" class="absolute inset-0 w-full h-full z-0 block"></canvas>

      <!-- Gradient Overlay for depth -->
      <div
        class="absolute inset-0 bg-gradient-radial from-transparent via-[#F4F4EE]/30 to-[#F4F4EE]/90 dark:via-slate-900/30 dark:to-slate-900/90">
      </div>
    </div>

    <!-- Main Content -->
    <!-- Added min-h to ensure vertical centering works against the full viewport minus header/footer if needed, but flex-1 in App.vue handles push. 
         However, to center content *vertically* in the available space, we need this container to grow.
    -->
    <div class="relative z-10 w-full flex-grow flex flex-col items-center justify-center gap-12 px-4">

      <!-- Minimalist Search -->
      <div class="w-full max-w-xl flex flex-col gap-6 animate-fade-in-up mt-10">
        <div class="relative group">
          <input v-model="inputChara" @keypress.enter="goToDetail" type="text" placeholder="在此輸入…"
            class="w-full bg-transparent border-b-2 border-gray-300 dark:border-slate-600 focus:border-[#D32913] dark:focus:border-[#D32913] py-4 text-center text-3xl font-light tracking-widest outline-none transition-colors duration-300 placeholder-gray-300 dark:placeholder-slate-700 text-slate-800 dark:text-slate-100">
        </div>

        <div class="flex justify-center gap-8 mt-4">
          <button @click="goToDetail"
            class="text-slate-500 hover:text-[#D32913] dark:text-slate-400 dark:hover:text-[#D32913] font-medium tracking-widest transition-colors duration-300 text-sm uppercase">
            檢索於通表
          </button>
          <button @click="router.push({ path: '/sheet', query: inputChara ? { q: inputChara } : {} })"
            class="text-slate-500 hover:text-[#D32913] dark:text-slate-400 dark:hover:text-[#D32913] font-medium tracking-widest transition-colors duration-300 text-sm uppercase">
            檢索於粵表
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<!-- Non-scoped styles for global keyframes to ensure JS strings match -->
<style>
/* 
   Optimized Animations:
   1. Use translate3d to force hardware acceleration.
   2. REMOVED rotation from keyframes to avoid re-rasterizing blurred elements.
*/
@keyframes bg-float {
  0% {
    transform: translate3d(0, 0, 0);
  }

  100% {
    transform: translate3d(40px, 30px, 0);
  }
}

@keyframes bg-float-reverse {
  0% {
    transform: translate3d(0, 0, 0);
  }

  100% {
    transform: translate3d(-40px, -30px, 0);
  }
}
</style>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 1s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
