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

  return {
    mode: isProduction ? "production" : "development",

    entry: {
      app: "./assets/js/index.js",
    },

    output: {
      path: path.resolve(__dirname, "resources/assets"),
      filename: isProduction ? "js/[contenthash].js" : "js/[name].js",
      assetModuleFilename: "assets/[hash][ext][query]",
      clean: true,
    },

    devtool: isDevelopment ? "source-map" : false,

    resolve: {
      extensions: [".mjs", ".js"],
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
            },
            {
              loader: "css-loader",
              options: {
                sourceMap: isDevelopment,
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
        {
          test: /\.(flv|vob|mp4|wmv|webm|ogv|avi|mov|m4v)$/,
          type: "asset/resource",
          generator: {
            filename: "videos/[contenthash][ext]",
          },
        },
        {
          test: /\.(png|jpe?g|gif|svg|webp|avif|ico)$/i,
          type: "asset/resource",
          generator: {
            filename: "images/[contenthash][ext]",
          },
        },
        {
          test: /\.(woff|woff2|ttf|otf|eot)$/,
          type: "asset/resource",
          generator: {
            filename: "fonts/[contenthash][ext]",
          },
        },
        {
          test: /\.html$/i,
          loader: "html-loader",
          options: {
            minimize: isProduction,
            sources: {
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
                      ((rel === "preload" || rel === "prefetch") &&
                        (as === "image" || as === "video" || as === "audio"))
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
        filename: isProduction ? "css/[contenthash].css" : "css/[name].css",
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
        if (!htmlFile.includes("compiled")) {
          return new HtmlWebpackPlugin({
            inject: htmlFile.includes("layouts") ? true : false,
            template: htmlFile,
            filename:
              "../../" +
              htmlFile.replace("assets\\views\\", "assets/views/compiled/"),
            publicPath: "@url",
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
        } else {
          return new Noop();
        }
      }),
    ],
  };
};
