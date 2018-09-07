const gulp = require('gulp');
const sass = require('gulp-sass');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const sourcemaps = require('gulp-sourcemaps');


const config = {
  paths: {
    sass: [
      './Resources/Private/Scss/**/*.scss',
      './Resources/Private/Scss/*.scss',
    ],
  },
  autoprefixer: {
    browsers: [
      'last 2 versions',
      'ie 11',
      'opera 12.1',
      'ios 9',
      'android 4'
    ],
    cascade: true
  }
};

const sassTask = () => {
  return gulp.src(config.paths.sass)
      .pipe(sass({
        outputStyle: 'expanded'
      }))
      .pipe(sourcemaps.init())
           .pipe(postcss([autoprefixer(config.autoprefixer)]))
           .pipe(sourcemaps.write('.'))
      .pipe(gulp.dest('./Resources/Public/Css/'))
};

const sassProd = () => {
  return gulp.src(config.paths.sass)
      .pipe(sass({
        outputStyle: 'expanded'
      }))
      .pipe(postcss([autoprefixer(config.autoprefixer)]))

      .pipe(gulp.dest('./Resources/Public/Css/'))
};

gulp.task('build', gulp.parallel([sassProd]));

const watch = () => {
  return gulp.watch(config.paths.sass, gulp.series([sassTask, sassProd]));
};

gulp.task('watch', gulp.series([watch]));

gulp.task('default', gulp.series([sassTask, watch]));
