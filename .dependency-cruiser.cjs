module.exports = {
  forbidden: [
    {
      name: 'no-circular',
      severity: 'error',
      from: {},
      to: { circular: true },
    },
    {
      name: 'shared-tanpa-features',
      severity: 'error',
      from: { path: '^src/shared/' },
      to: { path: '^src/features/' },
    },
    {
      name: 'antar-fitur-lewat-public-api',
      severity: 'error',
      from: { path: '^src/features/([^/]+)/' },
      to: {
        path: '^src/features/[^/]+/',
        pathNot: ['^src/features/$1/', '^src/features/[^/]+/index\\.ts$'],
      },
    },
    {
      name: 'service-tanpa-ui',
      severity: 'error',
      from: { path: '^src/features/[^/]+/services/' },
      to: {
        path: [
          '^src/features/[^/]+/(hooks|components|pages)/',
          '(^|/)node_modules/@vue/(runtime-core|runtime-dom)/',
        ],
      },
    },
    {
      name: 'ui-tanpa-http-langsung',
      severity: 'error',
      from: { path: '^src/features/[^/]+/(components|pages)/' },
      to: { path: '^src/shared/services/httpClient' },
    },
  ],
  options: {
    doNotFollow: { path: 'node_modules' },
    tsConfig: { fileName: 'tsconfig.json' },
    enhancedResolveOptions: { extensions: ['.ts', '.vue', '.js'] },
  },
};
