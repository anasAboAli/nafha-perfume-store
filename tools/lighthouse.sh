#!/usr/bin/env bash
# Mobile Lighthouse (PageSpeed's engine) for the key pages. Usage: tools/lighthouse.sh [label]
cd "$(dirname "$0")"
label="${1:-run}"
for page in "/" "/product/dior-sauvage-edp/" "/shop/"; do
  name=$(echo "$page" | tr '/' '_' | sed 's/^_$/home/; s/^_//; s/_$//')
  npx lighthouse "http://localhost:8080$page" --quiet --chrome-flags="--headless=new" --only-categories=performance,seo,accessibility,best-practices \
    --output=json --output-path="reports/${label}-${name:-home}.json" >/dev/null 2>&1
  node -e "
    const r=require('./reports/${label}-${name:-home}.json'); const c=r.categories, a=r.audits;
    const s=k=>Math.round((c[k]?.score||0)*100);
    console.log('$page'.padEnd(28), 'perf', s('performance'), '| seo', s('seo'), '| a11y', s('accessibility'), '| bp', s('best-practices'),
      '| LCP', a['largest-contentful-paint'].displayValue, '| TBT', a['total-blocking-time'].displayValue, '| CLS', a['cumulative-layout-shift'].displayValue, '| FCP', a['first-contentful-paint'].displayValue);"
done
