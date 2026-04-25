<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
  locations: {
    type: Array,
    required: true
  }
});

const mapContainer = ref(null);
let map = null;

const getFontColorArr = (color) => {
  const hex = color.replace('#', '');
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  if ((r + g + b) < 384) {
    return "#F4F4EE";
  } else {
    return "#2F2F2F";
  }
};

const initMap = () => {
    if (!window.L || !mapContainer.value) return;
    
    const leftup = L.latLng('26.938400','105.819028'); 
    const rightdown = L.latLng('17.119569','115.883134');
    const bounds = L.latLngBounds(leftup,rightdown);
    
    if (map) map.remove();
    
    map = L.map(mapContainer.value, {
        maxBounds: bounds,
        minZoom: 6,
        maxZoom: 12
    }).setView([22.6, 111], 7);
    map.attributionControl.setPrefix('');

    L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/256/{z}/{x}/{y}@2x?access_token={accessToken}', {
        tms: false,
        id: 'zenam/cjzk0fd9k0esv1csaqrxhiemj',
        accessToken: 'pk.eyJ1IjoiemVuYW0iLCJhIjoiY2p4bjh5MjFxMGM4aTNobGF0dXNoejlseiJ9.BPrObTer-_5w5L3oEaEWfQ',
        attribution: '©Mapbox | Performed By Lingnaam Jyutjam'
    }).addTo(map);
    
    // Group by coordinates to handle exact overlaps
    const coordMap = new Map();
    props.locations.forEach(entry => {
        if (entry.latitude && entry.longitude) {
            const key = `${entry.latitude},${entry.longitude}`;
            if (!coordMap.has(key)) coordMap.set(key, []);
            coordMap.get(key).push(entry);
        }
    });

    coordMap.forEach((entries, key) => {
        const [latStr, lngStr] = key.split(',');
        const baseLat = parseFloat(latStr);
        const baseLng = parseFloat(lngStr);
        
        // Render base circle if needed (just once per location?)
        // Or for each entry? If same location, maybe just one cycle?
        // Let's draw one circle per unique location to avoid Z-fighting opacity
        L.circle([baseLat, baseLng], {
             color: entries[0].color,
             fillColor: entries[0].color,
             fillOpacity: 0.5,
             radius: 1000
        }).addTo(map);

        // Spread markers if multiple at same spot
        entries.forEach((entry, idx) => {
            let lat = baseLat;
            let lng = baseLng;
            
            // Simple Spiral/Jitter for exact overlaps
            if (entries.length > 1) {
                const angle = (idx / entries.length) * 2 * Math.PI;
                const radius = 0.15; // approximate degrees offset? No, that's huge. 0.05 is better.
                // Actually 0.05 deg is about 5km.
                // We want visual separation.
                // Let's use a small offset like 0.02
                const offset = 0.02 + (0.01 * idx); 
                // Spiral out
                lat = baseLat + (Math.sin(angle) * offset * 0.7); // flatten slightly for perspective?
                lng = baseLng + (Math.cos(angle) * offset);
            }

            const prons = (entry['粵拼'] || []).join('<br>');
            const html = `
                <div class='locale-label' style='background-color: ${entry.color};'>
                    <span style='color: ${getFontColorArr(entry.color)}'>${prons}</span>
                </div>
            `;
            
            const marker = L.marker([lat, lng], {
                 draggable: true, // Allow user to fix overlaps manually
                 icon: L.divIcon({
                     className: 'divIconDefault',
                     html: html,
                     iconSize: [60, 24]
                 })
            }).addTo(map);

            // Leader line
            let polyline = null;
            const updateLine = () => {
                const curLatLng = marker.getLatLng();
                if (curLatLng.lat !== baseLat || curLatLng.lng !== baseLng) {
                     if (polyline) map.removeLayer(polyline);
                     polyline = L.polyline([[baseLat, baseLng], curLatLng], {
                         color: entry.color,
                         weight: 2.5,
                         dashArray: '5, 5',
                         opacity: 0.6
                     }).addTo(map);
                }
            };
            
            // Initial line if offset
            if (entries.length > 1) updateLine();

            marker.on('drag', updateLine);
        });
    });
};

onMounted(() => {
    if (window.L) {
        initMap();
    } else {
        const timer = setInterval(() => {
            if (window.L) {
                initMap();
                clearInterval(timer);
            }
        }, 100);
    }
});

watch(() => props.locations, () => {
    initMap();
});

</script>

<template>
  <div class="glass w-full h-[400px] rounded-lg overflow-hidden relative">
      <div ref="mapContainer" class="w-full h-full z-0"></div>
  </div>
</template>

<style>
.locale-label {
    padding: 1px 4px;
    border-radius: 2px;
    position: relative;
    text-align: center;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    font-size: 11px;
    font-family: monospace;
    opacity: 0.8;
    border: 1px solid rgba(255,255,255,0.3);
}
/* Ensure marker handles pointer events */
.leaflet-marker-icon {
    background: transparent;
    border: none;
}
</style>
