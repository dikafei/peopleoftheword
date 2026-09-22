document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.lesson-media-player').forEach(function (player) {
        var tabs        = player.querySelectorAll('.lesson-media-tab-btn');
        var panels      = player.querySelectorAll('.lesson-media-panel');
        var downloadBtn = player.querySelector('.lesson-media-download-btn');

        function setActiveTab(tab) {
            player.setAttribute('data-active', tab);

            tabs.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.tab === tab);
            });
            panels.forEach(function (panel) {
                panel.style.display = (panel.dataset.panel === tab) ? '' : 'none';
                var mediaEl = panel.querySelector('video, audio');
                if (mediaEl && panel.dataset.panel !== tab) {
                    mediaEl.pause();
                }
            });

            if (downloadBtn) {
                var url  = player.getAttribute('data-' + tab + '-url');
                var name = player.getAttribute('data-' + tab + '-name') || 'download';
                if (url) {
                    downloadBtn.setAttribute('href', url);
                    downloadBtn.setAttribute('download', name);
                }
            }
        }

        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setActiveTab(btn.dataset.tab);
            });
        });

        var audioPanel = player.querySelector('.lesson-media-audio-panel');
        if (audioPanel) {
            var audio     = audioPanel.querySelector('.lesson-media-audio-el');
            var playBtn   = audioPanel.querySelector('.lesson-media-play-btn');
            var iconPlay  = audioPanel.querySelector('.icon-play');
            var iconPause = audioPanel.querySelector('.icon-pause');
            var seek      = audioPanel.querySelector('.lesson-media-seek');
            var current   = audioPanel.querySelector('.lesson-media-current');
            var remaining = audioPanel.querySelector('.lesson-media-remaining');

            function formatTime(sec) {
                sec = Math.floor(sec || 0);
                var m = Math.floor(sec / 60);
                var s = sec % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }

            playBtn.addEventListener('click', function () {
                if (audio.paused) {
                    audio.play();
                    iconPlay.style.display = 'none';
                    iconPause.style.display = '';
                } else {
                    audio.pause();
                    iconPlay.style.display = '';
                    iconPause.style.display = 'none';
                }
            });

            audio.addEventListener('timeupdate', function () {
                if (audio.duration) {
                    seek.value = (audio.currentTime / audio.duration) * 100;
                    current.textContent = formatTime(audio.currentTime);
                    remaining.textContent = '-' + formatTime(audio.duration - audio.currentTime);
                }
            });

            seek.addEventListener('input', function () {
                if (audio.duration) {
                    audio.currentTime = (seek.value / 100) * audio.duration;
                }
            });

            audio.addEventListener('ended', function () {
                iconPlay.style.display = '';
                iconPause.style.display = 'none';
            });
        }
    });
});