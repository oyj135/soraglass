import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

// dist/ 是临时构建产物：scripts/release.mjs 构建后把它打进 ../soraglass.zip 并自动清理。
// 线上（阿里云 ECS + 宝塔）不运行 Node，部署必须上传 zip，而不是源码目录。
export default defineConfig({
  plugins: [tailwindcss()],
  publicDir: 'public',
  // 必须是相对基址：宝塔里站点可能装在子目录，CSS 里的字体 url 若带前导 / 会 404
  base: './',
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,
    target: 'es2020',
    rollupOptions: {
      input: { main: resolve(process.cwd(), 'src/scripts/main.ts') },
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
});
