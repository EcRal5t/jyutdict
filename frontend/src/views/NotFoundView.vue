<script setup>
import { useRouter } from 'vue-router'
import { ref, onMounted } from 'vue'

const router = useRouter()

const goHome = () => {
    router.push('/')
}

// Background Elements Generation
const shards = ref([])
const particles = ref([])

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

        return {
            id: i,
            style: {
                top: `${top}%`,
                left: `${left}%`,
                width: `${randomRange(150, 400)}px`,
                height: `${randomRange(150, 400)}px`,
                backgroundColor: colors[Math.floor(Math.random() * colors.length)],
                opacity: randomRange(0.04, 0.09), // Slight bump to ensure visibility
                filter: `blur(${randomRange(40, 90)}px)`,
                transform: `rotate(${randomRange(0, 360)}deg)`,
                clipPath: `polygon(${p1}, ${p2}, ${p3})`,
                // Animation props
                animationName: i % 2 === 0 ? 'bg-float' : 'bg-float-reverse',
                animationDuration: `${randomRange(15, 45)}s`,
                animationDelay: `${randomRange(-40, 0)}s`,
                animationTimingFunction: 'ease-in-out',
                animationIterationCount: 'infinite',
                animationDirection: 'alternate'
            }
        }
    })

    // Generate Particles (Small dots/dust)
    const particleCount = 50
    particles.value = Array.from({ length: particleCount }, (_, i) => {
        let top, left
        top = randomRange(0, 100)
        left = randomRange(0, 100)

        return {
            id: i,
            style: {
                top: `${top}%`,
                left: `${left}%`,
                width: `${randomRange(3, 6)}px`,
                height: `${randomRange(3, 6)}px`,
                backgroundColor: colors[Math.floor(Math.random() * colors.length)],
                opacity: 1, // Base opacity, controlled by keyframes
                borderRadius: '50%',
                filter: 'blur(1px)',
                // Animation props
                animationName: 'bg-particle-float',
                animationDuration: `${randomRange(10, 20)}s`,
                animationDelay: `${randomRange(-20, 0)}s`,
                animationTimingFunction: 'linear',
                animationIterationCount: 'infinite',
                // target opacity for randomness in CSS variable if needed, but simple keyframes work
                '--p-opacity': randomRange(0.2, 0.5)
            }
        }
    })
}

onMounted(() => {
    generateElements()
})
</script>

<template>
    <div
        class="relative w-full h-full min-h-[calc(100vh-14rem)] flex flex-col items-center justify-center overflow-hidden bg-[#F4F4EE] dark:bg-slate-900 transition-colors duration-300">

        <!-- Dynamic Background Layer -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none select-none">

            <!-- Shards -->
            <div v-for="shard in shards" :key="`shard-${shard.id}`" class="absolute will-change-transform"
                :style="shard.style"></div>

            <!-- Particles -->
            <div v-for="particle in particles" :key="`particle-${particle.id}`" class="absolute will-change-transform"
                :style="particle.style"></div>

            <!-- Gradient Overlay for depth -->
            <div
                class="absolute inset-0 bg-gradient-radial from-transparent via-[#F4F4EE]/30 to-[#F4F4EE]/90 dark:via-slate-900/30 dark:to-slate-900/90">
            </div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-4xl mx-auto px-4 flex flex-col items-center gap-12 text-center">

            <div class="flex flex-col gap-6 animate-fade-in-up mt-10 items-center">
                <h1 class="text-9xl font-light text-[#D32913] tracking-widest opacity-80 select-none font-serif">
                    404
                </h1>
                <h2 class="text-3xl font-light text-slate-800 dark:text-slate-100 tracking-wider">
                    趤失路啦？
                </h2>
                <p class="text-slate-500 dark:text-slate-400 font-light tracking-wide max-w-md">
                    The page you are looking for does not exist.
                </p>

                <div class="mt-8">
                    <button @click="goHome"
                        class="px-8 py-4 border-2 border-[#D32913] text-[#D32913] hover:bg-[#D32913] hover:text-white dark:hover:text-white transition-all duration-300 rounded-none tracking-widest uppercase text-sm font-bold shadow-[4px_4px_0_rgba(211,41,19,0.3)] hover:shadow-[6px_6px_0_rgba(211,41,19,0.4)] hover:-translate-y-1 active:translate-y-0 active:shadow-[2px_2px_0_rgba(211,41,19,0.3)]">
                        返首頁
                    </button>
                </div>
            </div>

        </div>
    </div>
</template>

<!-- Non-scoped styles for global keyframes to ensure JS strings match -->
<style>
@keyframes bg-float {
    0% {
        transform: translate(0, 0) rotate(0deg);
    }

    100% {
        transform: translate(40px, 30px) rotate(10deg);
    }
}

@keyframes bg-float-reverse {
    0% {
        transform: translate(0, 0) rotate(0deg);
    }

    100% {
        transform: translate(-40px, -30px) rotate(-10deg);
    }
}

@keyframes bg-particle-float {
    0% {
        transform: translateY(0) translateX(0);
        opacity: 0;
    }

    20% {
        opacity: var(--p-opacity, 0.4);
    }

    80% {
        opacity: var(--p-opacity, 0.4);
    }

    100% {
        transform: translateY(-80px) translateX(25px);
        opacity: 0;
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
