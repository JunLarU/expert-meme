const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const HtmlWebpackPlugin = require("html-webpack-plugin");
const glob = require("glob");
const fs = require("fs");
const os = require("os");
const zlib = require("zlib");
const { promisify } = require("util");
const { spawnSync } = require("child_process");
const ImageminPlugin = require("imagemin-webpack-plugin").default;
const CopyWebpackPlugin = require("copy-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");

// ======================================================
// .env
// ======================================================

const IMAGE_EXTENSIONS = /\.(png|jpe?g|gif|svg|webp|avif|ico)$/i;

const VIDEO_EXTENSIONS = /\.(mp4|webm|ogv|avi|mov|m4v|mkv|flv|vob|wmv)$/i;

const AUDIO_EXTENSIONS = /\.(mp3|wav|ogg|m4a|aac|flac)$/i;

const FONT_EXTENSIONS = /\.(woff|woff2|ttf|otf|eot)$/i;

const TEXT_TRACK_EXTENSIONS = /\.(vtt|srt)$/i;

const FILE_EXTENSIONS = /\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|7z)$/i;

const parseDotEnv = (filePath) => {
  if (!fs.existsSync(filePath)) return {};

  const env = {};
  const content = fs.readFileSync(filePath, "utf8");

  content.split(/\r?\n/).forEach((line) => {
    const trimmed = line.trim();

    if (!trimmed || trimmed.startsWith("#")) return;

    const match = trimmed.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);
    if (!match) return;

    const key = match[1];
    let value = match[2].trim();

    value = value.replace(/^['"]|['"]$/g, "");

    env[key] = value;
  });

  return env;
};

const projectEnv = parseDotEnv(path.resolve(__dirname, ".env"));

const envValue = (key, fallback = "") => {
  const value = process.env[key] ?? projectEnv[key];
  return value === undefined || value === null || value === ""
    ? fallback
    : String(value).trim();
};

const envBoolean = (key, fallback = false) => {
  const value = envValue(key, fallback ? "true" : "false").toLowerCase();

  if (["1", "true", "yes", "on"].includes(value)) return true;
  if (["0", "false", "no", "off"].includes(value)) return false;

  return fallback;
};

const envInteger = (key, fallback, min, max) => {
  const parsed = Number.parseInt(envValue(key, String(fallback)), 10);
  const safeValue = Number.isFinite(parsed) ? parsed : fallback;
  return Math.min(max, Math.max(min, safeValue));
};

const normalizeEnvironmentName = (value) =>
  String(value || "")
    .trim()
    .toLowerCase();

const gzipAsync = promisify(zlib.gzip);
const brotliAsync = zlib.brotliCompress ? promisify(zlib.brotliCompress) : null;

const normalizePublicUrl = (url) => {
  const value = String(url || "").trim();

  if (!value) return "/";

  return value.endsWith("/") ? value : `${value}/`;
};

// Esta URL SÍ se usará dentro de CSS/SCSS/JS final.
// Ejemplo:
// http://192.168.68.62/images/foto.hash.jpg
const APP_PUBLIC_PATH = normalizePublicUrl(
  process.env.APP_URL || projectEnv.APP_URL || "/",
);

// Esta URL se usará solo dentro de HTML compilado por Webpack.
// Luego StencilEngine cambia @url por config('app.url').
// Ejemplo:
// @url/images/foto.hash.jpg
const HTML_PUBLIC_PATH = "@url/";
// APP_URL sin slash final.
// Ejemplo:
// http://192.168.68.62
const APP_URL = APP_PUBLIC_PATH.replace(/\/+$/, "");

if (/[\r\n"'`]/.test(APP_URL)) {
  throw new Error(
    "APP_URL contiene caracteres inseguros para los assets compilados.",
  );
}

/**
 * Reemplaza @url en archivos que NO pasan por StencilEngine:
 *
 * JS:
 *   "@url/contact/send"
 *   => "http://192.168.68.62/contact/send"
 *
 * SCSS/CSS:
 *   url("@url/images/fondo.jpg")
 *   => url("http://192.168.68.62/images/fondo.jpg")
 *
 * Importante:
 * No se aplica al HTML porque el HTML sí pasa por StencilEngine.
 */
const replaceAtUrlLoader = {
  loader: "string-replace-loader",
  options: {
    search: /@url(?=\/|["'`)\s]|$)/g,
    replace: APP_URL,
  },
};

const toPosix = (value) => value.replace(/\\/g, "/");

const sourceToBuffer = (source) => {
  if (source && typeof source.buffer === "function") {
    return Buffer.from(source.buffer());
  }

  return Buffer.from(source.source());
};

const shouldProcessUrl = (url) => {
  if (!url) return false;

  const value = String(url).trim();

  // No procesar URLs externas, data URIs, anchors, mail, tel,
  // variables de Stencil/PHP ni rutas que ya vengan con @url.
  if (/^(?:https?:|data:|mailto:|tel:|#|@url)/i.test(value)) return false;
  if (/^(?:<\?(?:php|=)?|\{\{|\{%)/i.test(value)) return false;

  return true;
};

const makePreservedAssetName = (rootDir, outputFolder) => {
  return (pathData) => {
    const absoluteFile = pathData.filename;

    let relativeFile = toPosix(path.relative(rootDir, absoluteFile));

    // Si el archivo no está dentro del root esperado, conservar ruta desde /assets
    // en vez de colapsarlo a basename. Esto evita colisiones.
    if (relativeFile.startsWith("..")) {
      relativeFile = toPosix(
        path.relative(path.resolve(__dirname, "assets"), absoluteFile),
      );
    }

    // Evita duplicar carpeta:
    // assets/videos/demo.mp4 => videos/demo.hash.mp4
    // no videos/videos/demo.hash.mp4
    if (relativeFile.startsWith(`${outputFolder}/`)) {
      relativeFile = relativeFile.slice(outputFolder.length + 1);
    }

    const parsed = path.parse(relativeFile);
    const dir = toPosix(parsed.dir);
    const fileName = `${parsed.name}.[contenthash:8]${parsed.ext}`;

    return `${outputFolder}/${dir && dir !== "." ? `${dir}/` : ""}${fileName}`;
  };
};

const makeAssetRule = (test, rootDir, outputFolder) => {
  return {
    test,
    oneOf: [
      // Assets usados desde HTML:
      // <img src="/images/foto.jpg">
      // => @url/images/foto.hash.jpg
      {
        issuer: /\.html$/i,
        type: "asset/resource",
        generator: {
          filename: makePreservedAssetName(rootDir, outputFolder),
          publicPath: HTML_PUBLIC_PATH,
        },
      },

      // Assets usados desde SCSS/CSS:
      // background-image: url("/images/foto.jpg");
      // => http://192.168.68.62/images/foto.hash.jpg
      {
        issuer: /\.(sa|sc|c)ss$/i,
        type: "asset/resource",
        generator: {
          filename: makePreservedAssetName(rootDir, outputFolder),
          publicPath: APP_PUBLIC_PATH,
        },
      },

      // Assets importados desde JS u otros archivos:
      // import img from "/images/foto.jpg";
      // => http://192.168.68.62/images/foto.hash.jpg
      {
        type: "asset/resource",
        generator: {
          filename: makePreservedAssetName(rootDir, outputFolder),
          publicPath: APP_PUBLIC_PATH,
        },
      },
    ],
  };
};

class CleanCompiledViewsPlugin {
  constructor(directoryPath) {
    this.directoryPath = directoryPath;
  }

  clean() {
    fs.rmSync(this.directoryPath, {
      recursive: true,
      force: true,
    });

    fs.mkdirSync(this.directoryPath, {
      recursive: true,
    });
  }

  apply(compiler) {
    const pluginName = "CleanCompiledViewsPlugin";

    /*
     * Compilación normal:
     * npm run dev
     * npm run build
     */
    compiler.hooks.beforeRun.tap(pluginName, () => {
      this.clean();
    });

    /*
     * Compilación en modo watch:
     * npm run watch
     */
    compiler.hooks.watchRun.tap(pluginName, () => {
      this.clean();
    });
  }
}

class SeoAssetsPlugin {
  constructor(options = {}) {
    this.enabled = options.enabled ?? false;
    this.baseUrl = String(options.baseUrl || "").replace(/\/+$/, "");
    this.routes = options.routes ?? [];
  }

  emitOrUpdate(compilation, name, content, RawSource) {
    const source = new RawSource(content);

    if (compilation.getAsset(name)) {
      compilation.updateAsset(name, source);
      return;
    }

    compilation.emitAsset(name, source);
  }

  apply(compiler) {
    if (!this.enabled || !this.baseUrl) return;

    const pluginName = "SeoAssetsPlugin";

    compiler.hooks.thisCompilation.tap(pluginName, (compilation) => {
      const { Compilation, sources } = compiler.webpack;

      compilation.hooks.processAssets.tap(
        {
          name: pluginName,
          stage: Compilation.PROCESS_ASSETS_STAGE_ADDITIONAL,
        },
        () => {
          const robots = [
            "User-agent: *",
            "Allow: /",
            "Disallow: /admin/",
            "Disallow: /site-api/",
            "Disallow: /form",
            "",
            `Sitemap: ${this.baseUrl}/sitemap.xml`,
            "",
          ].join("\n");

          const lastModified = new Date().toISOString().slice(0, 10);
          const sitemapEntries = this.routes
            .map(({ path: routePath, changefreq, priority }) => {
              const location = `${this.baseUrl}${routePath === "/" ? "/" : routePath}`;

              return [
                "  <url>",
                `    <loc>${location}</loc>`,
                `    <lastmod>${lastModified}</lastmod>`,
                `    <changefreq>${changefreq}</changefreq>`,
                `    <priority>${priority}</priority>`,
                "  </url>",
              ].join("\n");
            })
            .join("\n");
          const sitemap = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            sitemapEntries,
            "</urlset>",
            "",
          ].join("\n");

          const manifest = JSON.stringify(
            {
              name: "L+E Ingeniería",
              short_name: "L+E Ingeniería",
              description:
                "Diseño estructural, construcción en acero, supervisión técnica y valuación inmobiliaria.",
              lang: "es-MX",
              start_url: "/",
              scope: "/",
              display: "standalone",
              background_color: "#ffffff",
              theme_color: "#1e293b",
              icons: [
                {
                  src: "/images/ICON.png",
                  sizes: "1500x1500",
                  type: "image/png",
                  purpose: "any maskable",
                },
              ],
            },
            null,
            2,
          );

          this.emitOrUpdate(
            compilation,
            "robots.txt",
            robots,
            sources.RawSource,
          );
          this.emitOrUpdate(
            compilation,
            "sitemap.xml",
            sitemap,
            sources.RawSource,
          );
          this.emitOrUpdate(
            compilation,
            "site.webmanifest",
            manifest,
            sources.RawSource,
          );
        },
      );
    });
  }
}

class PrecompressAssetsPlugin {
  constructor(options = {}) {
    this.enabled = options.enabled ?? false;
    this.minSize = options.minSize ?? 1024;
    this.test = options.test ?? /\.(?:js|css|svg|json|xml|txt|webmanifest)$/i;
  }

  apply(compiler) {
    if (!this.enabled) return;

    const pluginName = "PrecompressAssetsPlugin";

    compiler.hooks.thisCompilation.tap(pluginName, (compilation) => {
      const { Compilation, sources } = compiler.webpack;
      const stage =
        Compilation.PROCESS_ASSETS_STAGE_OPTIMIZE_TRANSFER ??
        Compilation.PROCESS_ASSETS_STAGE_REPORT;

      compilation.hooks.processAssets.tapPromise(
        { name: pluginName, stage },
        async () => {
          const assetNames = compilation
            .getAssets()
            .map(({ name }) => name)
            .filter(
              (name) =>
                !name.includes("..") &&
                !/\.(?:gz|br)$/i.test(name) &&
                this.test.test(name),
            );

          await Promise.all(
            assetNames.map(async (name) => {
              const asset = compilation.getAsset(name);
              if (!asset) return;

              const original = sourceToBuffer(asset.source);
              if (original.length < this.minSize) return;

              const gzip = await gzipAsync(original, {
                level: zlib.constants.Z_BEST_COMPRESSION,
              });

              if (gzip.length < original.length * 0.98) {
                compilation.emitAsset(
                  `${name}.gz`,
                  new sources.RawSource(gzip),
                  {
                    compressed: true,
                    immutable: asset.info.immutable,
                    related: { source: name },
                  },
                );
              }

              if (!brotliAsync) return;

              const brotli = await brotliAsync(original, {
                params: {
                  [zlib.constants.BROTLI_PARAM_QUALITY]: 11,
                  [zlib.constants.BROTLI_PARAM_MODE]:
                    zlib.constants.BROTLI_MODE_TEXT,
                },
              });

              if (brotli.length < original.length * 0.98) {
                compilation.emitAsset(
                  `${name}.br`,
                  new sources.RawSource(brotli),
                  {
                    compressed: true,
                    immutable: asset.info.immutable,
                    related: { source: name },
                  },
                );
              }
            }),
          );
        },
      );
    });
  }
}

class MediaOptimizationPlugin {
  constructor(options = {}) {
    this.optimizeVideos = options.optimizeVideos ?? false;
    this.generateAvif = options.generateAvif ?? false;
    this.avifQuality = options.avifQuality ?? 55;
    this.videoCrf = options.videoCrf ?? 25;
    this.ffmpegPath = options.ffmpegPath || "ffmpeg";
    this.minVideoSize = options.minVideoSize ?? 512 * 1024;
    this.minImageSize = options.minImageSize ?? 8 * 1024;
  }

  ffmpegIsAvailable() {
    const result = spawnSync(this.ffmpegPath, ["-version"], {
      stdio: "ignore",
      windowsHide: true,
      timeout: 10000,
    });

    return result.status === 0;
  }

  transcodeBuffer(input, inputExtension, outputExtension, args) {
    const tempDirectory = fs.mkdtempSync(
      path.join(os.tmpdir(), "whis-webpack-media-"),
    );
    const inputPath = path.join(tempDirectory, `input${inputExtension}`);
    const outputPath = path.join(tempDirectory, `output${outputExtension}`);

    try {
      fs.writeFileSync(inputPath, input);

      const result = spawnSync(
        this.ffmpegPath,
        [
          "-hide_banner",
          "-loglevel",
          "error",
          "-y",
          "-i",
          inputPath,
          ...args,
          outputPath,
        ],
        {
          stdio: "ignore",
          windowsHide: true,
          timeout: 15 * 60 * 1000,
        },
      );

      if (result.status !== 0 || !fs.existsSync(outputPath)) return null;

      return fs.readFileSync(outputPath);
    } finally {
      fs.rmSync(tempDirectory, { recursive: true, force: true });
    }
  }

  videoArguments(extension) {
    if ([".mp4", ".m4v", ".mov"].includes(extension)) {
      return [
        "-map_metadata",
        "-1",
        "-map",
        "0:v:0",
        "-map",
        "0:a?",
        "-sn",
        "-c:v",
        "libx264",
        "-preset",
        "slow",
        "-crf",
        String(this.videoCrf),
        "-pix_fmt",
        "yuv420p",
        "-c:a",
        "aac",
        "-b:a",
        "128k",
        "-movflags",
        "+faststart",
      ];
    }

    if (extension === ".webm") {
      return [
        "-map_metadata",
        "-1",
        "-map",
        "0:v:0",
        "-map",
        "0:a?",
        "-sn",
        "-c:v",
        "libvpx-vp9",
        "-b:v",
        "0",
        "-crf",
        String(Math.min(45, this.videoCrf + 7)),
        "-row-mt",
        "1",
        "-c:a",
        "libopus",
        "-b:a",
        "96k",
      ];
    }

    if (extension === ".ogv") {
      return [
        "-map_metadata",
        "-1",
        "-c:v",
        "libtheora",
        "-q:v",
        "7",
        "-c:a",
        "libvorbis",
        "-q:a",
        "4",
      ];
    }

    return null;
  }

  apply(compiler) {
    if (!this.optimizeVideos && !this.generateAvif) return;

    const pluginName = "MediaOptimizationPlugin";
    const logger = compiler.getInfrastructureLogger(pluginName);

    compiler.hooks.thisCompilation.tap(pluginName, (compilation) => {
      const { Compilation, sources } = compiler.webpack;

      compilation.hooks.processAssets.tapPromise(
        {
          name: pluginName,
          stage: Compilation.PROCESS_ASSETS_STAGE_OPTIMIZE_SIZE,
        },
        async () => {
          if (!this.ffmpegIsAvailable()) {
            logger.warn(
              `No se encontró FFmpeg en "${this.ffmpegPath}". Se omite la conversión AVIF y la optimización de video sin detener el build.`,
            );
            return;
          }

          const assets = [...compilation.getAssets()];

          for (const asset of assets) {
            const extension = path.extname(asset.name).toLowerCase();
            const input = sourceToBuffer(asset.source);

            if (
              this.optimizeVideos &&
              VIDEO_EXTENSIONS.test(asset.name) &&
              input.length >= this.minVideoSize
            ) {
              const videoArgs = this.videoArguments(extension);

              if (videoArgs) {
                const optimized = this.transcodeBuffer(
                  input,
                  extension,
                  extension,
                  videoArgs,
                );

                if (optimized && optimized.length < input.length * 0.99) {
                  compilation.updateAsset(
                    asset.name,
                    new sources.RawSource(optimized),
                    { ...asset.info, minimized: true },
                  );
                }
              }
            }

            if (
              this.generateAvif &&
              /\.jpe?g$/i.test(asset.name) &&
              input.length >= this.minImageSize
            ) {
              const avifName = asset.name.replace(/\.jpe?g$/i, ".avif");
              if (compilation.getAsset(avifName)) continue;

              const avifCrf = Math.min(
                50,
                Math.max(18, Math.round(63 - this.avifQuality * 0.5)),
              );
              const avif = this.transcodeBuffer(input, extension, ".avif", [
                "-an",
                "-frames:v",
                "1",
                "-c:v",
                "libaom-av1",
                "-still-picture",
                "1",
                "-crf",
                String(avifCrf),
                "-b:v",
                "0",
                "-pix_fmt",
                "yuv420p",
              ]);

              if (avif && avif.length < input.length) {
                compilation.emitAsset(avifName, new sources.RawSource(avif), {
                  immutable: asset.info.immutable,
                  minimized: true,
                  related: { source: asset.name },
                });
              }
            }
          }
        },
      );
    });
  }
}

class InjectAvifPictureSourcesPlugin {
  constructor(options = {}) {
    this.enabled = options.enabled ?? false;
  }

  apply(compiler) {
    if (!this.enabled) return;

    const pluginName = "InjectAvifPictureSourcesPlugin";

    compiler.hooks.thisCompilation.tap(pluginName, (compilation) => {
      const { Compilation, sources } = compiler.webpack;

      compilation.hooks.processAssets.tap(
        {
          name: pluginName,
          stage: Compilation.PROCESS_ASSETS_STAGE_SUMMARIZE,
        },
        () => {
          for (const asset of compilation.getAssets()) {
            if (!/\.html$/i.test(asset.name)) continue;

            const html = asset.source.source().toString();
            const transformed = html.replace(
              /<img\b([^>]*?)(data-src|src)=["'](@url\/images\/([^"']+)\.(jpe?g))["']([^>]*)>/gi,
              (match, before, attribute, fullUrl, relativeBase) => {
                const avifAssetName = `images/${relativeBase}.avif`;
                if (!compilation.getAsset(avifAssetName)) return match;

                const sourceAttribute =
                  attribute.toLowerCase() === "data-src"
                    ? "data-srcset"
                    : "srcset";

                return `<picture><source type="image/avif" ${sourceAttribute}="@url/${avifAssetName}">${match}</picture>`;
              },
            );

            if (transformed !== html) {
              compilation.updateAsset(
                asset.name,
                new sources.RawSource(transformed),
                asset.info,
              );
            }
          }
        },
      );
    });
  }
}

module.exports = (env = {}, argv = {}) => {
  const appEnvironment = normalizeEnvironmentName(envValue("APP_ENV"));
  const nodeEnvironment = normalizeEnvironmentName(envValue("NODE_ENV"));
  const declaredEnvironments = [appEnvironment, nodeEnvironment].filter(
    Boolean,
  );
  const isProduction = declaredEnvironments.length
    ? declaredEnvironments.every((value) =>
        ["production", "prod"].includes(value),
      )
    : argv.mode === "production";
  const isDevelopment = !isProduction;

  if (isProduction && !/^https?:\/\//i.test(APP_URL)) {
    throw new Error(
      "APP_URL debe ser una URL absoluta http(s) cuando APP_ENV y NODE_ENV están en production.",
    );
  }

  const optimizeImages =
    isProduction && envBoolean("WEBPACK_OPTIMIZE_IMAGES", true);
  const optimizeVideos =
    isProduction && envBoolean("WEBPACK_OPTIMIZE_VIDEOS", true);
  const precompressAssets =
    isProduction && envBoolean("WEBPACK_PRECOMPRESS", true);
  const dropDebugConsole =
    isProduction && envBoolean("WEBPACK_DROP_DEBUG_CONSOLE", true);
  const imageQuality = envInteger("WEBPACK_IMAGE_QUALITY", 82, 40, 100);
  const avifQuality = envInteger("WEBPACK_AVIF_QUALITY", 55, 20, 95);
  const videoCrf = envInteger("WEBPACK_VIDEO_CRF", 25, 18, 36);

  /*
   * imagemin-webpack-plugin@2.4.2 utiliza una versión antigua
   * de pngquant que espera la calidad como "min-max",
   * usando números enteros entre 0 y 100.
   *
   * Con WEBPACK_IMAGE_QUALITY=82:
   * pngQualityRange será "70-82".
   */
  const pngQualityMin = Math.max(0, imageQuality - 12);
  const pngQualityMax = Math.max(pngQualityMin, imageQuality);
  const pngQualityRange = `${pngQualityMin}-${pngQualityMax}`;
  const assetsRoot = path.resolve(__dirname, "assets");
  const imagesRoot = path.resolve(__dirname, "assets/images");
  const videosRoot = path.resolve(__dirname, "assets/videos");
  const fontsRoot = path.resolve(__dirname, "assets/fonts");
  const audioRoot = path.resolve(__dirname, "assets/audio");
  const filesRoot = path.resolve(__dirname, "assets/files");

  const viewsRoot = path.resolve(__dirname, "assets/views");
  const compiledViewsRoot = path.resolve(viewsRoot, "compiled");

  /*
   * Solo toma vistas fuente.
   *
   * Muy importante:
   * assets/views/compiled queda excluido directamente desde glob.
   * De esta forma una vista ya compilada nunca vuelve a entrar como
   * plantilla durante la siguiente compilación.
   */
  const viewTemplateFiles = glob
    .sync("**/*.html", {
      cwd: viewsRoot,
      absolute: true,
      nodir: true,
      ignore: ["compiled/**"],
      windowsPathsNoEscape: true,
    })
    .sort((a, b) => a.localeCompare(b));

  const htmlViewPlugins = viewTemplateFiles.map((htmlFile) => {
    const relativeViewPath = toPosix(path.relative(viewsRoot, htmlFile));

    const isLayout = relativeViewPath.startsWith("layouts/");

    const isAdminLayout = relativeViewPath === "layouts/admin/layout.html";

    return new HtmlWebpackPlugin({
      /*
       * Archivo fuente.
       */
      template: htmlFile,

      /*
       * Archivo final.
       *
       * Por ejemplo:
       *
       * assets/views/pages/main/home.html
       *
       * se genera como:
       *
       * assets/views/compiled/pages/main/home.html
       *
       * La ruta absoluta evita depender manualmente de:
       * ../../assets/views/compiled
       */
      filename: path.resolve(compiledViewsRoot, relativeViewPath),

      /*
       * Solo los layouts reciben CSS y JS.
       *
       * Los includes y páginas parciales se compilan, pero no reciben
       * nuevamente scripts ni hojas de estilo.
       */
      inject: isLayout,

      chunks: isLayout ? [isAdminLayout ? "admin" : "app"] : [],

      publicPath: HTML_PUBLIC_PATH,
      scriptLoading: "defer",

      /*
       * Tus vistas no usan parámetros EJS de HtmlWebpackPlugin.
       * Utilizan StencilEngine, por eso se deshabilitan.
       */
      templateParameters: false,

      /*
       * Evita reutilizar un resultado inválido del child compiler.
       * Esto es importante al ejecutar npm run build varias veces.
       */
      cache: false,

      minify: isProduction
        ? {
            collapseWhitespace: true,
            conservativeCollapse: true,
            collapseBooleanAttributes: true,
            removeComments: true,

            /*
             * Debe permanecer en false porque algunas vistas usan
             * atributos vacíos intencionales y sintaxis dinámica.
             */
            removeEmptyAttributes: false,

            removeRedundantAttributes: true,
            removeScriptTypeAttributes: true,
            removeStyleLinkTypeAttributes: true,
            sortAttributes: false,
            sortClassName: false,
            useShortDoctype: true,
          }
        : false,
    });
  });

  return {
    mode: isProduction ? "production" : "development",

    cache: {
      type: "filesystem",
      cacheDirectory: path.resolve(__dirname, ".cache/webpack"),
      buildDependencies: {
        config: [__filename],
      },
    },

    entry: {
      app: "./assets/js/index.js",
      admin: "./assets/js/admin/index.js",
    },

    output: {
      path: path.resolve(__dirname, "resources/assets"),

      // Importante:
      // Este publicPath queda dentro de JS runtime y CSS final.
      // Por eso NO debe ser @url.
      publicPath: APP_PUBLIC_PATH,

      filename: isProduction ? "js/[contenthash:8].js" : "js/[name].js",
      chunkFilename: isProduction ? "js/[contenthash:8].js" : "js/[name].js",
      assetModuleFilename: "assets/[contenthash:8][ext][query]",
      hashFunction: "xxhash64",
      clean: true,
    },

    devtool: isDevelopment ? "source-map" : false,

    resolve: {
      extensions: [".mjs", ".js"],

      // Permite usar rutas absolutas desde /assets:
      //
      // /images/foto.jpg
      // /images/subfolder/foto.jpg
      // /videos/demo.mp4
      // /fonts/font.woff2
      roots: [assetsRoot],

      alias: {
        "@assets": assetsRoot,
        "@images": imagesRoot,
        "@videos": videosRoot,
        "@fonts": fontsRoot,
        "@audio": audioRoot,
        "@files": filesRoot,
      },
    },

    module: {
      rules: [
        {
          test: /\.m?js$/,
          exclude: /(node_modules|bower_components)/,
          use: [
            {
              loader: "babel-loader",
              options: {
                cacheDirectory: true,
                cacheCompression: false,
                presets: [
                  [
                    "@babel/preset-env",
                    {
                      bugfixes: true,
                      modules: false,
                      targets: isProduction
                        ? "> 0.5%, not dead, not op_mini all"
                        : "last 2 Chrome versions, last 2 Firefox versions, last 2 Edge versions",
                    },
                  ],
                ],
              },
            },
            replaceAtUrlLoader,
          ],
        },

        {
          test: /\.(sa|sc|c)ss$/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                publicPath: APP_PUBLIC_PATH,
              },
            },
            {
              loader: "css-loader",
              options: {
                sourceMap: isDevelopment,
                importLoaders: 3,
                url: {
                  filter: shouldProcessUrl,
                },
              },
            },
            {
              loader: "postcss-loader",
              options: {
                sourceMap: isDevelopment,
                postcssOptions: {
                  plugins: [
                    require("autoprefixer")({
                      grid: "autoplace",
                    }),
                  ],
                },
              },
            },

            // Reemplaza @url después de compilar SASS,
            // pero antes de que css-loader empaquete el CSS.
            replaceAtUrlLoader,

            {
              loader: "sass-loader",
              options: {
                implementation: require("sass"),
                sourceMap: isDevelopment,
                sassOptions: {
                  outputStyle: "expanded",
                },
              },
            },
          ],
        },
        makeAssetRule(IMAGE_EXTENSIONS, imagesRoot, "images"),

        makeAssetRule(VIDEO_EXTENSIONS, videosRoot, "videos"),

        makeAssetRule(AUDIO_EXTENSIONS, audioRoot, "audio"),

        makeAssetRule(FONT_EXTENSIONS, fontsRoot, "fonts"),

        makeAssetRule(TEXT_TRACK_EXTENSIONS, assetsRoot, "tracks"),

        makeAssetRule(FILE_EXTENSIONS, filesRoot, "files"),
        {
          test: /\.html$/i,
          loader: "html-loader",
          options: {
            minimize: isProduction,
            sources: {
              urlFilter: (attribute, value) => shouldProcessUrl(value),

              list: [
                // =========================
                // Imágenes normales/lazy
                // =========================
                { tag: "img", attribute: "src", type: "src" },
                { tag: "img", attribute: "data-src", type: "src" },
                { tag: "img", attribute: "srcset", type: "srcset" },
                { tag: "img", attribute: "data-srcset", type: "srcset" },

                { tag: "picture", attribute: "data-src", type: "src" },

                // =========================
                // Source: sirve para picture, video y audio
                // =========================
                { tag: "source", attribute: "src", type: "src" },
                { tag: "source", attribute: "data-src", type: "src" },
                { tag: "source", attribute: "srcset", type: "srcset" },
                { tag: "source", attribute: "data-srcset", type: "srcset" },

                // =========================
                // Videos
                // =========================
                { tag: "video", attribute: "src", type: "src" },
                { tag: "video", attribute: "data-src", type: "src" },

                { tag: "video", attribute: "poster", type: "src" },
                { tag: "video", attribute: "data-poster", type: "src" },

                // Variantes responsive/custom para tu lazyloader
                { tag: "video", attribute: "data-mobile-src", type: "src" },
                { tag: "video", attribute: "data-tablet-src", type: "src" },
                { tag: "video", attribute: "data-desktop-src", type: "src" },

                { tag: "video", attribute: "data-src-mobile", type: "src" },
                { tag: "video", attribute: "data-src-tablet", type: "src" },
                { tag: "video", attribute: "data-src-desktop", type: "src" },

                // =========================
                // Subtítulos/captions
                // =========================
                { tag: "track", attribute: "src", type: "src" },
                { tag: "track", attribute: "data-src", type: "src" },

                // =========================
                // Audio
                // =========================
                { tag: "audio", attribute: "src", type: "src" },
                { tag: "audio", attribute: "data-src", type: "src" },

                // =========================
                // Backgrounds genéricos
                // =========================
                { tag: "div", attribute: "data-background-src", type: "src" },
                {
                  tag: "section",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "article",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "header",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "footer",
                  attribute: "data-background-src",
                  type: "src",
                },
                { tag: "main", attribute: "data-background-src", type: "src" },
                { tag: "li", attribute: "data-background-src", type: "src" },
                { tag: "span", attribute: "data-background-src", type: "src" },
                { tag: "a", attribute: "data-background-src", type: "src" },

                // Variantes responsive para fondos lazy.
                ...[
                  "div",
                  "section",
                  "article",
                  "header",
                  "footer",
                  "main",
                  "li",
                  "span",
                  "a",
                ].flatMap((tag) => [
                  { tag, attribute: "data-background-mobile-src", type: "src" },
                  { tag, attribute: "data-background-tablet-src", type: "src" },
                  {
                    tag,
                    attribute: "data-background-desktop-src",
                    type: "src",
                  },
                ]),

                // =========================
                // Background videos
                // =========================
                { tag: "div", attribute: "data-video-src", type: "src" },
                { tag: "section", attribute: "data-video-src", type: "src" },
                { tag: "article", attribute: "data-video-src", type: "src" },
                { tag: "header", attribute: "data-video-src", type: "src" },
                { tag: "footer", attribute: "data-video-src", type: "src" },

                {
                  tag: "div",
                  attribute: "data-background-video-src",
                  type: "src",
                },
                {
                  tag: "section",
                  attribute: "data-background-video-src",
                  type: "src",
                },
                {
                  tag: "article",
                  attribute: "data-background-video-src",
                  type: "src",
                },
                {
                  tag: "header",
                  attribute: "data-background-video-src",
                  type: "src",
                },
                {
                  tag: "footer",
                  attribute: "data-background-video-src",
                  type: "src",
                },

                { tag: "div", attribute: "data-video-poster", type: "src" },
                { tag: "section", attribute: "data-video-poster", type: "src" },
                { tag: "article", attribute: "data-video-poster", type: "src" },
                { tag: "header", attribute: "data-video-poster", type: "src" },
                { tag: "footer", attribute: "data-video-poster", type: "src" },

                // =========================
                // Preload / prefetch
                // =========================
                {
                  tag: "link",
                  attribute: "href",
                  type: "src",
                  filter: (tag, attribute, attributes) => {
                    const rel = attributes.rel;
                    const as = attributes.as;

                    return (
                      rel === "icon" ||
                      rel === "shortcut icon" ||
                      rel === "apple-touch-icon" ||
                      ((rel === "preload" || rel === "prefetch") &&
                        (as === "image" ||
                          as === "video" ||
                          as === "audio" ||
                          as === "font" ||
                          as === "track"))
                    );
                  },
                },

                // =========================
                // Open Graph / Twitter cards
                // =========================
                {
                  tag: "meta",
                  attribute: "content",
                  type: "src",
                  filter: (tag, attribute, attributes) => {
                    const property = attributes.property || "";
                    const name = attributes.name || "";

                    return (
                      property === "og:image" ||
                      property === "og:image:secure_url" ||
                      property === "og:video" ||
                      property === "og:video:url" ||
                      property === "og:video:secure_url" ||
                      name === "twitter:image" ||
                      name === "twitter:player"
                    );
                  },
                },
              ],
            },
          },
        },
      ],
    },

    optimization: {
      /*
       * Webpack >= 5.95 lo activa automáticamente en production.
       *
       * HtmlWebpackPlugin compila todas las plantillas mediante un
       * child compiler. En determinadas plantillas esta optimización
       * puede dejar dos declaraciones de __webpack_exports__ en el
       * mismo resultado y vm.Script no puede evaluarlo.
       */
      avoidEntryIife: false,

      minimize: isProduction,
      minimizer: isProduction
        ? [
            new TerserPlugin({
              parallel: true,
              extractComments: false,
              terserOptions: {
                ecma: 2018,
                compress: {
                  passes: 2,
                  drop_debugger: true,
                  pure_funcs: dropDebugConsole
                    ? [
                        "console.log",
                        "console.debug",
                        "console.info",
                        "console.trace",
                        "console.table",
                        "console.group",
                        "console.groupCollapsed",
                        "console.groupEnd",
                        "console.time",
                        "console.timeEnd",
                      ]
                    : [],
                },
                mangle: true,
                format: {
                  comments: false,
                },
              },
            }),
            new CssMinimizerPlugin({
              parallel: true,
              minimizerOptions: {
                preset: [
                  "default",
                  {
                    discardComments: { removeAll: true },
                  },
                ],
              },
            }),
          ]
        : [],
      moduleIds: isProduction ? "deterministic" : "named",
      chunkIds: isProduction ? "deterministic" : "named",
      usedExports: isProduction,
      sideEffects: true,
      concatenateModules: isProduction,
      splitChunks: isProduction
        ? {
            chunks: "all",
            minSize: 20000,
            maxSize: 220000,
            maxAsyncRequests: 20,
            maxInitialRequests: 12,
            cacheGroups: {
              vendors: {
                test: /[\\/]node_modules[\\/]/,
                name: "vendors",
                priority: 20,
                reuseExistingChunk: true,
              },
              common: {
                name: "common",
                minChunks: 2,
                priority: 10,
                reuseExistingChunk: true,
              },
            },
          }
        : false,
      /*
 * Solo tienes dos entradas independientes: app y admin.
 * Incluir el runtime en cada entrada evita conflictos con el
 * compilador interno de HtmlWebpackPlugin y elimina una petición
 * adicional por página.
 */
runtimeChunk: false,
    },

    performance: {
      hints: isProduction ? "warning" : false,
      maxEntrypointSize: 500000,
      maxAssetSize: 500000,
      assetFilter: (assetFilename) => /\.(?:js|css)$/i.test(assetFilename),
    },

    stats: {
      preset: isProduction ? "normal" : "errors-warnings",
      assets: true,
      modules: false,
      children: false,
    },

    plugins: [
      new MiniCssExtractPlugin({
        filename: isProduction ? "css/[contenthash:8].css" : "css/[name].css",
        chunkFilename: isProduction
          ? "css/[contenthash:8].css"
          : "css/[name].css",
      }),

      /*
 * Limpia únicamente las vistas compiladas.
 *
 * No borra resources/views ni modifica Compilation.assets.
 */
new CleanCompiledViewsPlugin(compiledViewsRoot),

      new CopyWebpackPlugin({
        patterns: [
          {
            from: path.resolve(__dirname, "assets/images/ICON.png"),
            to: path.resolve(__dirname, "resources/assets/images/ICON.png"),
            noErrorOnMissing: true,
          },
          {
            from: path.resolve(__dirname, "assets/images/og-image.jpg"),
            to: path.resolve(__dirname, "resources/assets/images/og-image.jpg"),
            noErrorOnMissing: true,
          },
          {
            from: path.resolve(__dirname, "assets/robots.txt"),
            to: path.resolve(__dirname, "resources/assets/robots.txt"),
            noErrorOnMissing: true,
          },
          {
            from: path.resolve(__dirname, "assets/sitemap.xml"),
            to: path.resolve(__dirname, "resources/assets/sitemap.xml"),
            noErrorOnMissing: true,
          },
        ],
      }),

      new SeoAssetsPlugin({
        enabled: isProduction,
        baseUrl: APP_URL,
        routes: [
          { path: "/", changefreq: "weekly", priority: "1.0" },
          { path: "/nosotros", changefreq: "monthly", priority: "0.8" },
          { path: "/proyectos", changefreq: "weekly", priority: "0.9" },
          {
            path: "/servicio/estructura",
            changefreq: "monthly",
            priority: "0.9",
          },
          {
            path: "/servicio/valuacion",
            changefreq: "monthly",
            priority: "0.9",
          },
          { path: "/contacto", changefreq: "monthly", priority: "0.8" },
        ],
      }),

      ...(optimizeImages
        ? [
            new ImageminPlugin({
              test: /\.(?:jpe?g|png|gif|svg)$/i,
              cacheFolder: path.resolve(__dirname, ".cache/imagemin"),
              onlyUseIfSmaller: true,
              optipng: {
                optimizationLevel: 7,
              },
              gifsicle: {
                optimizationLevel: 3,
                interlaced: true,
              },
              jpegtran: {
                progressive: true,
              },
              svgo: {},
              pngquant: {
                quality: pngQualityRange,
                speed: 1,
                strip: true,
              },
            }),
            new MediaOptimizationPlugin({
              optimizeVideos,
              generateAvif: true,
              avifQuality,
              videoCrf,
              ffmpegPath: envValue("FFMPEG_PATH", "ffmpeg"),
            }),
            new InjectAvifPictureSourcesPlugin({ enabled: true }),
          ]
        : optimizeVideos
          ? [
              new MediaOptimizationPlugin({
                optimizeVideos: true,
                generateAvif: false,
                avifQuality,
                videoCrf,
                ffmpegPath: envValue("FFMPEG_PATH", "ffmpeg"),
              }),
            ]
          : []),

      new PrecompressAssetsPlugin({
        enabled: precompressAssets,
      }),

      ...htmlViewPlugins,
    ],
  };
};
