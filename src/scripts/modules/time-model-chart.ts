/**
 * 时光机「使用过的模型」柱状图（2026-11-25）：
 *   形态对照用户给的柱状图（圆角渐变柱 + 虚线网格 + 极值水滴标记），配色改用主题朱砂。
 *   数据源直接读服务端已渲染的 .sg-time-model 横条（名称 + 占比），不另塞 JSON；
 *   横条列表保留为读屏内容，仅在 JS 成功后视觉隐藏（无 JS 时原样可读）。
 *   昼夜切换靠 MutationObserver 监听 html.dark 重绘（canvas 不认 CSS 变量）；
 *   PJAX 换页由 lifecycle 的 onPageDispose 统一 dispose 实例与观察器。
 */
import * as echarts from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, MarkPointComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import type { EChartsCoreOption } from 'echarts/core';
import { onPageDispose } from './lifecycle';

echarts.use([BarChart, GridComponent, TooltipComponent, MarkPointComponent, CanvasRenderer]);

/** 读当前主题 token（canvas 不能用 var()，取值后传给 ECharts） */
const token = (name: string, fallback: string): string =>
  getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

export function initTimeModelChart(): void {
  const section = document.querySelector<HTMLElement>('[data-sg-model-chart]');
  if (!section || section.classList.contains('is-charted')) return;
  const list = section.querySelector<HTMLElement>('.sg-time-model-list');
  if (!list) return;

  const models = Array.from(list.querySelectorAll<HTMLElement>('.sg-time-model')).map((li) => ({
    name: li.querySelector('.sg-time-model-name')?.textContent?.trim() ?? '',
    ratio: Number.parseFloat(li.querySelector('.sg-time-model-ratio')?.textContent ?? '0') || 0,
  })).filter((m) => m.name && m.ratio > 0);
  if (!models.length) return;

  const host = document.createElement('div');
  host.className = 'sg-time-model-chart';
  host.setAttribute('aria-hidden', 'true'); // 数据由保留的横条列表供读屏
  section.insertBefore(host, list);

  const chart = echarts.init(host, undefined, { renderer: 'canvas' });
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const maxRatio = Math.max(...models.map((m) => m.ratio));
  // 轴顶留 ~15% 余量（对齐参考图最高柱不贴顶的观感），上限 100%
  const axisMax = Math.min(100, Math.max(5, Math.ceil(maxRatio * 1.15 / 5) * 5));

  const buildOption = (): EChartsCoreOption => {
    const deep = token('--sg-warm', '#9d3b2f');
    const light = token('--sg-warm-bright', '#c96a5b');
    const line = token('--sg-line', 'rgba(37,34,41,.14)');
    const muted = token('--sg-muted', '#5f6563');
    const ink = token('--sg-text', '#252229');
    const card = token('--sg-card-strong', '#fdfbf4');

    return {
      animation: !reduced,
      animationDuration: 900,
      animationEasing: 'cubicOut',
      grid: { left: 8, right: 14, top: 38, bottom: 4, containLabel: true },
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        backgroundColor: card,
        borderColor: line,
        textStyle: { color: ink },
        formatter: '{b}<br/>占比 {c}%',
      },
      xAxis: {
        type: 'category',
        data: models.map((m) => m.name),
        axisLine: { lineStyle: { color: line } },
        axisTick: { show: false },
        axisLabel: {
          color: muted,
          interval: 0,
          fontSize: 11,
          formatter: (value: string) => value.length > 8 ? `${value.slice(0, 7)}…` : value,
        },
      },
      yAxis: {
        type: 'value',
        min: 0,
        max: axisMax,
        splitNumber: 4,
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: { color: muted, formatter: '{value}%' },
        splitLine: { lineStyle: { color: line, type: 'dashed' } },
      },
      series: [{
        type: 'bar',
        data: models.map((m) => m.ratio),
        barMaxWidth: 44,
        itemStyle: {
          borderRadius: [7, 7, 0, 0],
          // 上浅下深朱砂渐变（源站紫色渐变的 Soraglass 换色版）
          color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: light },
            { offset: 1, color: deep },
          ]),
        },
        // 极值水滴标记（参考图最高/最低柱上的 pin）
        markPoint: {
          symbol: 'pin',
          symbolSize: [34, 44],
          label: { show: true, position: 'inside', color: '#fff', fontSize: 11, fontWeight: 700, formatter: '{c}%' },
          itemStyle: { color: deep },
          data: [
            { type: 'max', name: '最高' },
            { type: 'min', name: '最低' },
          ],
        },
      }],
    } as EChartsCoreOption;
  };

  chart.setOption(buildOption());
  section.classList.add('is-charted');

  // 尺寸跟随容器（窄屏 / PJAX 布局切换）
  const ro = new ResizeObserver(() => chart.resize());
  ro.observe(host);

  // 昼夜切换：canvas 颜色是渲染时写死的，class 翻转后重取 token 重绘
  let dark = document.documentElement.classList.contains('dark');
  const mo = new MutationObserver(() => {
    const next = document.documentElement.classList.contains('dark');
    if (next === dark) return;
    dark = next;
    chart.setOption(buildOption(), { notMerge: true, lazyUpdate: false });
  });
  mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  onPageDispose(() => {
    ro.disconnect();
    mo.disconnect();
    chart.dispose();
  });
}
