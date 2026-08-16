/**
 * 地点多色辅助工具
 * 支持 "#RRGGBB" 或 "#RRGGBB,#RRGGBB,..." 逗号分隔的多色格式
 */

/**
 * 解析地点颜色字符串为颜色数组
 * @param {string} colorStr 逗号分隔的颜色字符串
 * @param {string} defaultColor 默认回退颜色
 * @returns {string[]} 颜色 Hex 数组
 */
export function parseAreaColors(colorStr, defaultColor = '#999999') {
    if (!colorStr || typeof colorStr !== 'string') {
        return defaultColor ? [defaultColor] : [];
    }
    const colors = colorStr
        .split(',')
        .map(c => c.trim().toUpperCase())
        .filter(c => /^#[0-9A-F]{6}$/i.test(c));

    if (colors.length === 0 && defaultColor) {
        return [defaultColor.toUpperCase()];
    }
    return colors;
}

/**
 * 获取主色（首个有效颜色）
 * @param {string} colorStr
 * @param {string} defaultColor
 * @returns {string}
 */
export function getPrimaryAreaColor(colorStr, defaultColor = '#999999') {
    const colors = parseAreaColors(colorStr, defaultColor);
    return colors[0] || defaultColor;
}

/**
 * 获取 CSS 背景属性值（单色或线性渐变）
 * @param {string|string[]} colorInput 颜色字符串或颜色数组
 * @param {string} direction 渐变方向，如 'to right', 'to bottom', '90deg'
 * @param {string} defaultColor
 * @returns {string}
 */
export function getAreaColorGradient(colorInput, direction = 'to right', defaultColor = '#999999') {
    const colors = Array.isArray(colorInput)
        ? colorInput.map(c => c.trim().toUpperCase()).filter(c => /^#[0-9A-F]{6}$/i.test(c))
        : parseAreaColors(colorInput, defaultColor);

    if (colors.length === 0) return defaultColor;
    if (colors.length === 1) return colors[0];
    return `linear-gradient(${direction}, ${colors.join(', ')})`;
}

/**
 * 根据颜色明度计算合适的文本对比色（深色或浅色）
 * @param {string} colorStr
 * @returns {string} '#F4F4EE' 或 '#2F2F2F'
 */
export function getContrastTextColor(colorStr) {
    const primary = getPrimaryAreaColor(colorStr);
    const hex = primary.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return (r * 0.299 + g * 0.587 + b * 0.114) < 140 ? '#F4F4EE' : '#2F2F2F';
}
