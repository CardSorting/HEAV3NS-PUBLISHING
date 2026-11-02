module.exports = {
    plugins: [
        require('autoprefixer'),
        require('cssnano')({
            preset: ['default', {
                discardComments: { removeAll: true },
                // postcss-calc cannot parse relative-color channel math yet;
                // browsers must evaluate hsl(from ... calc(l +/- n)) at runtime.
                calc: false,
                // Bootstrap already optimizes its encoded control icons.
                svgo: false,
            }],
        }),
    ],
};
