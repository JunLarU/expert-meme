const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const HtmlWebpackPlugin = require("html-webpack-plugin");
const glob = require("glob");
const RemovePlugin = require("remove-files-webpack-plugin");
const Noop = require("noop-webpack-plugin");
const fs = require("fs");
const ImageminPlugin = require("imagemin-webpack-plugin").default;
const pngquant = require("imagemin-pngquant");
const CopyWebpackPlugin = require("copy-webpack-plugin");

// ======================================================
// .env
// ======================================================

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

const normalizePublicUrl = (url) => {
  const value = String(url || "").trim();

  if (!value) return "/";

  return value.endsWith("/") ? value : `${value}/`;
};

// Esta URL SÍ se usará dentro de CSS/SCSS/JS final.
// Ejemplo:
// http://192.168.68.62/images/foto.hash.jpg
const APP_PUBLIC_PATH = normalizePublicUrl(
  process.env.APP_URL || projectEnv.APP_URL || "/"
);

// Esta URL se usará solo dentro de HTML compilado por Webpack.
// Luego StencilEngine cambia @url por config('app.url').
// Ejemplo:
// @url/images/foto.hash.jpg
const HTML_PUBLIC_PATH = "@url/";

const toPosix = (value) => value.replace(/\\/g, "/");

const shouldProcessUrl = (url) => {
  if (!url) return false;

  // No procesar URLs externas, data URIs, anchors, mail, tel,
  // ni rutas que ya vengan con @url.
  return !/^(?:https?:|data:|mailto:|tel:|#|@url)/i.test(url);
};

const makePreservedAssetName = (rootDir, outputFolder) => {
  return (pathData) => {
    const absoluteFile = pathData.filename;
    let relativeFile = toPosix(path.relative(rootDir, absoluteFile));

    if (relativeFile.startsWith("..")) {
      relativeFile = path.basename(absoluteFile);
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

class CreateDirectoryPlugin {
  constructor(directoryPath) {
    this.directoryPath = directoryPath;
  }

  apply(compiler) {
    compiler.hooks.emit.tap("CreateDirectoryPlugin", () => {
      if (!fs.existsSync(this.directoryPath)) {
        fs.mkdirSync(this.directoryPath, { recursive: true });
      }
    });
  }
}

module.exports = (env, argv) => {
  const isProduction = argv.mode === "production";
  const isDevelopment = !isProduction;

  const assetsRoot = path.resolve(__dirname, "assets");
  const imagesRoot = path.resolve(__dirname, "assets/images");
  const videosRoot = path.resolve(__dirname, "assets/videos");
  const fontsRoot = path.resolve(__dirname, "assets/fonts");
  const audioRoot = path.resolve(__dirname, "assets/audio");
  const filesRoot = path.resolve(__dirname, "assets/files");

  return {
    mode: isProduction ? "production" : "development",

    entry: {
      app: "./assets/js/index.js",
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
          use: {
            loader: "babel-loader",
            options: {
              cacheDirectory: true,
              presets: ["@babel/preset-env"],
            },
          },
        },

        {
          test: /\.(sa|sc|c)ss$/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader,
              options: {
                // Importante:
                // El CSS no pasa por StencilEngine, entonces aquí usamos APP_URL real.
                publicPath: APP_PUBLIC_PATH,
              },
            },
            {
              loader: "css-loader",
              options: {
                sourceMap: isDevelopment,
                url: {
                  filter: shouldProcessUrl,
                },
              },
            },
            {
              loader: "postcss-loader",
              options: {
                sourceMap: isDevelopment,
              },
            },
            {
              loader: "sass-loader",
              options: {
                implementation: require("sass"),
                sourceMap: isDevelopment,
              },
            },
          ],
        },

        makeAssetRule(
          /\.(png|jpe?g|gif|svg|webp|avif|ico)$/i,
          imagesRoot,
          "images"
        ),

        makeAssetRule(
          /\.(flv|vob|mp4|wmv|webm|ogv|avi|mov|m4v)$/i,
          videosRoot,
          "videos"
        ),

        makeAssetRule(
          /\.(mp3|wav|ogg|m4a|aac|flac)$/i,
          audioRoot,
          "audio"
        ),

        makeAssetRule(
          /\.(woff|woff2|ttf|otf|eot)$/i,
          fontsRoot,
          "fonts"
        ),

        makeAssetRule(
          /\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|7z)$/i,
          filesRoot,
          "files"
        ),

        {
          test: /\.html$/i,
          loader: "html-loader",
          options: {
            minimize: isProduction,
            sources: {
              urlFilter: (attribute, value) => shouldProcessUrl(value),

              list: [
                {
                  tag: "img",
                  attribute: "src",
                  type: "src",
                },
                {
                  tag: "img",
                  attribute: "data-src",
                  type: "src",
                },
                {
                  tag: "img",
                  attribute: "srcset",
                  type: "srcset",
                },
                {
                  tag: "img",
                  attribute: "data-srcset",
                  type: "srcset",
                },

                {
                  tag: "picture",
                  attribute: "data-src",
                  type: "src",
                },

                {
                  tag: "source",
                  attribute: "src",
                  type: "src",
                },
                {
                  tag: "source",
                  attribute: "data-src",
                  type: "src",
                },
                {
                  tag: "source",
                  attribute: "srcset",
                  type: "srcset",
                },
                {
                  tag: "source",
                  attribute: "data-srcset",
                  type: "srcset",
                },

                {
                  tag: "div",
                  attribute: "data-background-src",
                  type: "src",
                },
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
                {
                  tag: "main",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "li",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "span",
                  attribute: "data-background-src",
                  type: "src",
                },
                {
                  tag: "a",
                  attribute: "data-background-src",
                  type: "src",
                },

                {
                  tag: "video",
                  attribute: "src",
                  type: "src",
                },
                {
                  tag: "video",
                  attribute: "data-src",
                  type: "src",
                },
                {
                  tag: "video",
                  attribute: "poster",
                  type: "src",
                },
                {
                  tag: "video",
                  attribute: "data-poster",
                  type: "src",
                },

                {
                  tag: "track",
                  attribute: "src",
                  type: "src",
                },

                {
                  tag: "audio",
                  attribute: "src",
                  type: "src",
                },
                {
                  tag: "audio",
                  attribute: "data-src",
                  type: "src",
                },

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
                          as === "font"))
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
      minimize: isProduction,
      splitChunks: isProduction
        ? {
            chunks: "all",
            minSize: 20000,
            maxSize: 240000,
          }
        : false,
      runtimeChunk: isProduction ? "single" : false,
    },

    plugins: [
      new MiniCssExtractPlugin({
        filename: isProduction ? "css/[contenthash:8].css" : "css/[name].css",
        chunkFilename: isProduction ? "css/[contenthash:8].css" : "css/[name].css",
      }),

      ...(isProduction
        ? [
            new ImageminPlugin({
              plugins: [
                pngquant({
                  quality: [0.5, 0.7],
                }),
              ],
            }),
          ]
        : []),

      new RemovePlugin({
        before: {
          include: ["./assets/views/compiled", "./resources/views"],
        },
      }),

      new CreateDirectoryPlugin(path.resolve(__dirname, "resources", "views")),

      new CopyWebpackPlugin({
        patterns: [
          {
            from: path.resolve(__dirname, "assets/images/ICON.png"),
            to: path.resolve(__dirname, "resources/assets/images/ICON.png"),
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

      ...glob.sync("./assets/views/**/*.html").map((htmlFile) => {
        const normalizedHtmlFile = toPosix(htmlFile);

        if (normalizedHtmlFile.includes("/compiled/")) {
          return new Noop();
        }

        const compiledFilename =
          "../../" +
          normalizedHtmlFile.replace(
            /^\.?\/?assets\/views\//,
            "assets/views/compiled/"
          );

        return new HtmlWebpackPlugin({
          inject: normalizedHtmlFile.includes("/layouts/"),
          template: htmlFile,
          filename: compiledFilename,

          // Importante:
          // Solo el HTML usa @url porque luego pasa por StencilEngine.
          publicPath: HTML_PUBLIC_PATH,

          minify: isProduction
            ? {
                collapseWhitespace: true,
                removeComments: true,
                removeRedundantAttributes: true,
                removeScriptTypeAttributes: true,
                removeStyleLinkTypeAttributes: true,
                useShortDoctype: true,
              }
            : false,
        });
      }),
    ],
  };
};