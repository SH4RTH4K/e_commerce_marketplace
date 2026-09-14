export function money(amount) {
  return '৳' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

export function classNames(...classes) {
  return classes.filter(Boolean).join(' ');
}

export function imageUrl(path, seed = 'SHARTHAK') {
  if (path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return 'https://images.weserv.nl/?url=' + encodeURIComponent(path) + '&w=1200&q=75&output=webp';
    }
    if (path.startsWith('/uploads/') || path.startsWith('uploads/')) {
      return path.startsWith('/') ? path : '/' + path;
    }
    return path.startsWith('/') ? '/storage' + path : '/storage/' + path;
  }
  
  const label = (seed || 'No image').substring(0, 28);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="720" viewBox="0 0 600 720">
  <rect width="600" height="720" fill="#f5f5f4"/>
  <rect x="40" y="40" width="520" height="640" rx="24" fill="#e7e5e4"/>
  <text x="300" y="370" text-anchor="middle" fill="#a8a29e" font-family="system-ui,sans-serif" font-size="26">${label}</text>
</svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

export function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    if (!file) {
      resolve('');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = (error) => reject(error);
    reader.readAsDataURL(file);
  });
}
