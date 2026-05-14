/**
 * Voice Recorder Module
 * Uses browser MediaRecorder API to capture audio and send to AI backend.
 * Supports Hindi + English mixed input.
 */
(function (window) {
  'use strict';

  const VoiceRecorder = {
    mediaRecorder: null,
    audioChunks:   [],
    stream:        null,
    timerInterval: null,
    startTime:     0,
    isRecording:   false,

    /**
     * Check if browser supports recording
     */
    isSupported() {
      return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    },

    /**
     * Start recording
     * @returns {Promise<void>}
     */
    async start() {
      if (this.isRecording) return;
      if (!this.isSupported()) {
        throw new Error('Voice recording is not supported in this browser. Please use Chrome, Edge, or Safari.');
      }

      try {
        this.stream = await navigator.mediaDevices.getUserMedia({
          audio: {
            echoCancellation: true,
            noiseSuppression: true,
            sampleRate: 16000,
          },
        });
      } catch (err) {
        if (err.name === 'NotAllowedError') {
          throw new Error('Microphone permission denied. Please allow microphone access.');
        }
        throw new Error('Could not access microphone: ' + err.message);
      }

      this.audioChunks = [];
      const options    = this._getBestMimeType();

      try {
        this.mediaRecorder = new MediaRecorder(this.stream, options);
      } catch (e) {
        // Fallback to no options
        this.mediaRecorder = new MediaRecorder(this.stream);
      }

      this.mediaRecorder.ondataavailable = (e) => {
        if (e.data.size > 0) {
          this.audioChunks.push(e.data);
        }
      };

      this.mediaRecorder.start(100); // Collect in 100ms chunks
      this.isRecording = true;
      this.startTime   = Date.now();
      this._startTimer();
    },

    /**
     * Stop recording and return audio blob
     * @returns {Promise<{blob: Blob, mimeType: string, durationMs: number}>}
     */
    stop() {
      return new Promise((resolve, reject) => {
        if (!this.isRecording || !this.mediaRecorder) {
          reject(new Error('Not recording'));
          return;
        }

        this.mediaRecorder.onstop = () => {
          const mimeType  = this.mediaRecorder.mimeType || 'audio/webm';
          const blob      = new Blob(this.audioChunks, { type: mimeType });
          const durationMs = Date.now() - this.startTime;

          this._cleanup();
          resolve({ blob, mimeType, durationMs });
        };

        this.mediaRecorder.stop();
        this.isRecording = false;
        this._stopTimer();
      });
    },

    /**
     * Cancel recording without processing
     */
    cancel() {
      if (this.mediaRecorder && this.isRecording) {
        this.mediaRecorder.stop();
      }
      this.isRecording = false;
      this._stopTimer();
      this._cleanup();
    },

    /**
     * Convert audio blob to base64
     * @param {Blob} blob
     * @returns {Promise<string>}
     */
    blobToBase64(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => {
          const base64 = reader.result.split(',')[1];
          resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },

    /**
     * Get recording duration as formatted string (M:SS)
     */
    getFormattedDuration() {
      const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
      const minutes = Math.floor(elapsed / 60);
      const seconds = elapsed % 60;
      return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    },

    // ── Private ─────────────────────────────────────────────────

    _getBestMimeType() {
      const types = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/mp4',
      ];

      for (const type of types) {
        if (MediaRecorder.isTypeSupported(type)) {
          return { mimeType: type };
        }
      }
      return {};
    },

    _startTimer() {
      this.startTime = Date.now();
      this.timerInterval = setInterval(() => {
        const el = document.getElementById('aiVoiceTimer');
        if (el) el.textContent = this.getFormattedDuration();
      }, 500);
    },

    _stopTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },

    _cleanup() {
      if (this.stream) {
        this.stream.getTracks().forEach(t => t.stop());
        this.stream = null;
      }
      this.audioChunks  = [];
      this.mediaRecorder = null;
    },
  };

  window.VoiceRecorder = VoiceRecorder;

})(window);
