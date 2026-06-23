const FACE = {
    modelsLoaded: false,
    nativeDetector: null,
    stream: null,
    video: null,
    faceApiLoaded: false,
    detectionAvailable: false,
    modelsPath: 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/',

    async init() {
        let hadNative = false;
        if (typeof window !== 'undefined' && window.FaceDetector) {
            try {
                FACE.nativeDetector = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
                hadNative = true;
            } catch (e) {
                FACE.nativeDetector = null;
            }
        }

        if (typeof faceapi !== 'undefined' && !FACE.faceApiLoaded) {
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri(FACE.modelsPath);
                FACE.faceApiLoaded = true;
            } catch (e) {
                console.warn('face-api.js models not loaded:', e.message);
            }
        }

        FACE.detectionAvailable = hadNative || FACE.faceApiLoaded;
        if (!FACE.detectionAvailable) {
            console.warn('No face detection available. Use Chrome/Edge for best results.');
        }
        return true;
    },

    async detectFace(imageSource) {
        let faces = [];

        if (FACE.nativeDetector) {
            try {
                const detections = await FACE.nativeDetector.detect(imageSource);
                if (detections && detections.length > 0) {
                    faces = detections.map(d => ({
                        x: d.boundingBox.x, y: d.boundingBox.y,
                        width: d.boundingBox.width, height: d.boundingBox.height,
                        confidence: d.detectedConfidence || 1
                    }));
                }
            } catch (e) { }
        }

        if (faces.length === 0 && FACE.faceApiLoaded) {
            try {
                const detections = await faceapi.detectAllFaces(imageSource, new faceapi.TinyFaceDetectorOptions());
                if (detections && detections.length > 0) {
                    faces = detections.map(d => ({
                        x: d.box.x, y: d.box.y,
                        width: d.box.width, height: d.box.height,
                        confidence: d.score
                    }));
                }
            } catch (e) { }
        }

        return faces;
    },

    async startCamera(videoEl, width = 480, height = 360) {
        FACE.video = videoEl;
        try {
            const s = await navigator.mediaDevices.getUserMedia({
                video: { width, height, facingMode: 'user', frameRate: { ideal: 15 } }
            });
            FACE.stream = s;
            videoEl.srcObject = s;
            await videoEl.play();
            await FACE.init();
            return true;
        } catch (e) {
            throw new Error('Camera access denied: ' + e.message);
        }
    },

    stopCamera() {
        if (FACE.stream) {
            FACE.stream.getTracks().forEach(t => t.stop());
            FACE.stream = null;
        }
    },

    captureFrame(videoEl) {
        const canvas = document.createElement('canvas');
        canvas.width = videoEl.videoWidth || 480;
        canvas.height = videoEl.videoHeight || 360;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoEl, 0, 0);
        return { canvas, dataUrl: canvas.toDataURL('image/jpeg', 0.85) };
    },

    _cropAndLBP(source, cropX, cropY, cropW, cropH) {
        const canvas = document.createElement('canvas');
        canvas.width = 160;
        canvas.height = 160;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(source, cropX, cropY, cropW, cropH, 0, 0, 160, 160);

        const imageData2 = ctx.getImageData(0, 0, 160, 160);
        const grayData = new Uint8Array(160 * 160);
        for (let i = 0; i < grayData.length; i++) {
            const idx = i * 4;
            grayData[i] = Math.round(
                imageData2.data[idx] * 0.299 +
                imageData2.data[idx + 1] * 0.587 +
                imageData2.data[idx + 2] * 0.114
            );
        }
        return FACE._computeLBP(grayData, 160, 160);
    },

    _faceCrop(face, imgW, imgH) {
        const margin = 0.15;
        const x = Math.max(0, face.x - face.width * margin);
        const y = Math.max(0, face.y - face.height * margin);
        return {
            x, y,
            w: Math.min(imgW - x, face.width * (1 + 2 * margin)),
            h: Math.min(imgH - y, face.height * (1 + 2 * margin))
        };
    },

    async getDescriptor(imageData) {
        return new Promise(async (resolve) => {
            try {
                const img = new Image();
                img.onload = async function () {
                    const faces = await FACE.detectFace(img);
                    if (faces && faces.length > 0) {
                        const crop = FACE._faceCrop(faces[0], img.width, img.height);
                        resolve(FACE._cropAndLBP(img, crop.x, crop.y, crop.w, crop.h));
                    } else {
                        resolve(FACE._cropAndLBP(img, 0, 0, img.width, img.height));
                    }
                };
                img.onerror = () => resolve(null);
                img.src = imageData;
            } catch (e) {
                resolve(null);
            }
        });
    },

    async getDescriptorFromVideo(videoEl) {
        try {
            if (!videoEl || !videoEl.videoWidth) return null;
            const faces = await FACE.detectFace(videoEl);
            const canvas = document.createElement('canvas');
            canvas.width = videoEl.videoWidth;
            canvas.height = videoEl.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoEl, 0, 0);
            if (faces && faces.length > 0) {
                const crop = FACE._faceCrop(faces[0], canvas.width, canvas.height);
                return FACE._cropAndLBP(canvas, crop.x, crop.y, crop.w, crop.h);
            }
            return FACE._cropAndLBP(canvas, 0, 0, canvas.width, canvas.height);
        } catch (e) {
            return null;
        }
    },

    _computeLBP(grayData, width, height) {
        const radius = 2;
        const points = 8;
        const codes = [];

        for (let y = radius; y < height - radius; y += 3) {
            for (let x = radius; x < width - radius; x += 3) {
                const center = grayData[y * width + x];
                let pattern = 0;
                for (let i = 0; i < points; i++) {
                    const angle = (2 * Math.PI * i) / points;
                    const px = Math.round(x + radius * Math.cos(angle));
                    const py = Math.round(y + radius * Math.sin(angle));
                    if (grayData[py * width + px] >= center) pattern |= (1 << i);
                }
                codes.push(pattern);
            }
        }

        const histSize = 256;
        const hist = new Array(histSize).fill(0);
        for (const val of codes) {
            if (val < histSize) hist[val]++;
        }

        const bins = 32;
        const binSize = histSize / bins;
        const reduced = new Array(bins).fill(0);
        for (let i = 0; i < bins; i++) {
            const start = Math.floor(i * binSize);
            const end = Math.floor((i + 1) * binSize);
            for (let j = start; j < end && j < hist.length; j++) {
                reduced[i] += hist[j];
            }
        }

        const total = reduced.reduce((s, v) => s + v, 0) || 1;
        for (let i = 0; i < bins; i++) {
            reduced[i] = reduced[i] / total;
        }

        return reduced;
    },

    compareDescriptors(desc1, desc2) {
        if (!desc1 || !desc2 || desc1.length !== desc2.length) return 0;
        let similarity = 0;
        for (let i = 0; i < desc1.length; i++) {
            similarity += Math.min(desc1[i], desc2[i]);
        }
        return similarity;
    },

    async verifyFace(capturedDescriptor, storedDescriptor, threshold = 0.35) {
        const similarity = FACE.compareDescriptors(capturedDescriptor, storedDescriptor);
        return {
            match: similarity >= threshold,
            similarity: similarity,
            threshold: threshold
        };
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = FACE;
}
