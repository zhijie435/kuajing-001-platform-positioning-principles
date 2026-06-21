export function generateDeviceFingerprint() {
  const components = [
    navigator.userAgent,
    navigator.language,
    screen.width + 'x' + screen.height,
    screen.colorDepth,
    new Date().getTimezoneOffset(),
    navigator.platform,
    navigator.hardwareConcurrency || '',
    navigator.deviceMemory || '',
    (navigator.cookieEnabled ? '1' : '0')
  ]
  let hash = 0
  const str = components.join('|')
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i)
    hash = ((hash << 5) - hash) + char
    hash = hash & hash
  }
  return Math.abs(hash).toString(16) + Math.abs(hash * 7).toString(36)
}

export function getFingerprint() {
  let fp = localStorage.getItem('device_fingerprint')
  if (!fp) {
    fp = generateDeviceFingerprint()
    localStorage.setItem('device_fingerprint', fp)
  }
  return fp
}
