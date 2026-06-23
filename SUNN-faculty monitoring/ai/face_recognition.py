#!/usr/bin/env python3
"""
SUNN Faculty Monitoring System - AI Face Recognition Module
Requires: pip install opencv-python face_recognition numpy
"""

import cv2
import face_recognition
import numpy as np
import json
import sys
import os
import base64
import io
from PIL import Image

KNOWN_FACES_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'faces')
TEMP_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'temp')

def ensure_dirs():
    os.makedirs(KNOWN_FACES_DIR, exist_ok=True)
    os.makedirs(TEMP_DIR, exist_ok=True)

def encode_face(image_path):
    """Extract face encoding from an image file."""
    try:
        image = face_recognition.load_image_file(image_path)
        encodings = face_recognition.face_encodings(image)
        if len(encodings) == 0:
            return {'success': False, 'error': 'No face detected in the image'}
        encoding = encodings[0]
        return {'success': True, 'encoding': encoding.tolist(), 'num_faces': len(encodings)}
    except Exception as e:
        return {'success': False, 'error': str(e)}

def encode_face_from_base64(image_base64):
    """Extract face encoding from a base64 image string."""
    try:
        image_data = base64.b64decode(image_base64.split(',')[1] if ',' in image_base64 else image_base64)
        image = Image.open(io.BytesIO(image_data))
        image_array = np.array(image.convert('RGB'))
        encodings = face_recognition.face_encodings(image_array)
        if len(encodings) == 0:
            return {'success': False, 'error': 'No face detected'}
        return {'success': True, 'encoding': encodings[0].tolist(), 'num_faces': len(encodings)}
    except Exception as e:
        return {'success': False, 'error': str(e)}

def compare_faces(known_encoding, unknown_encoding, tolerance=0.6):
    """Compare two face encodings."""
    try:
        known = np.array(known_encoding)
        unknown = np.array(unknown_encoding)
        distance = np.linalg.norm(known - unknown)
        match = bool(face_recognition.compare_faces([known], unknown, tolerance=tolerance)[0])
        confidence = 1.0 - min(distance / 2.0, 1.0)
        return {
            'success': True,
            'match': match,
            'distance': float(distance),
            'confidence': float(confidence)
        }
    except Exception as e:
        return {'success': False, 'error': str(e)}

def find_best_match(unknown_encoding, known_encodings, tolerance=0.6):
    """Find the best matching known face."""
    best_match = None
    best_confidence = 0
    for instructor_id, encoding in known_encodings:
        result = compare_faces(encoding, unknown_encoding, tolerance)
        if result['success'] and result['match'] and result['confidence'] > best_confidence:
            best_match = instructor_id
            best_confidence = result['confidence']
    return best_match, best_confidence

def detect_faces(image_path):
    """Detect faces in an image and return locations."""
    try:
        image = face_recognition.load_image_file(image_path)
        face_locations = face_recognition.face_locations(image)
        return {
            'success': True,
            'num_faces': len(face_locations),
            'locations': [{'top': t, 'right': r, 'bottom': b, 'left': l} for t, r, b, l in face_locations]
        }
    except Exception as e:
        return {'success': False, 'error': str(e)}

def process_live_frame(image_base64, known_encodings_json, tolerance=0.6):
    """Process a live camera frame for face recognition."""
    try:
        image_data = base64.b64decode(image_base64.split(',')[1] if ',' in image_base64 else image_base64)
        image = Image.open(io.BytesIO(image_data))
        image_array = np.array(image.convert('RGB'))

        face_locations = face_recognition.face_locations(image_array)
        face_encodings = face_recognition.face_encodings(image_array, face_locations)

        if len(face_encodings) == 0:
            return {'success': True, 'faces_detected': 0, 'matches': []}

        known_encodings = json.loads(known_encodings_json) if isinstance(known_encodings_json, str) else known_encodings_json

        results = []
        for i, encoding in enumerate(face_encodings):
            best_id, confidence = find_best_match(encoding.tolist(), known_encodings, tolerance)
            loc = face_locations[i]
            results.append({
                'face_index': i,
                'location': {'top': loc[0], 'right': loc[1], 'bottom': loc[2], 'left': loc[3]},
                'matched_instructor_id': best_id,
                'confidence': round(confidence, 4) if best_id else 0,
                'is_known': best_id is not None
            })

        return {
            'success': True,
            'faces_detected': len(results),
            'matches': results
        }
    except Exception as e:
        return {'success': False, 'error': str(e)}

def anti_spoofing_check(image_path):
    """Basic anti-spoofing check using eye blink detection area."""
    try:
        image = cv2.imread(image_path)
        if image is None:
            return {'success': False, 'error': 'Cannot read image'}
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        eye_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_eye.xml')

        faces = face_cascade.detectMultiScale(gray, 1.1, 5)
        if len(faces) == 0:
            return {'success': False, 'error': 'No face detected', 'spoof_score': 1.0}

        eyes_detected = 0
        for (x, y, w, h) in faces:
            roi_gray = gray[y:y+h, x:x+w]
            eyes = eye_cascade.detectMultiScale(roi_gray)
            eyes_detected += len(eyes)

        score = 0.0 if eyes_detected > 0 else 0.5
        return {
            'success': True,
            'faces_detected': len(faces),
            'eyes_detected': eyes_detected,
            'spoof_score': score,
            'is_live': eyes_detected > 0
        }
    except Exception as e:
        return {'success': False, 'error': str(e)}

if __name__ == '__main__':
    ensure_dirs()
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'No action specified'}))
        sys.exit(1)

    action = sys.argv[1]
    if action == 'encode':
        if len(sys.argv) < 3:
            print(json.dumps({'success': False, 'error': 'No image path provided'}))
        else:
            result = encode_face(sys.argv[2])
            print(json.dumps(result))
    elif action == 'encode_base64':
        if len(sys.argv) < 3:
            print(json.dumps({'success': False, 'error': 'No image data provided'}))
        else:
            result = encode_face_from_base64(sys.argv[2])
            print(json.dumps(result))
    elif action == 'compare':
        if len(sys.argv) < 5:
            print(json.dumps({'success': False, 'error': 'Need known_encoding, unknown_encoding, tolerance'}))
        else:
            known = json.loads(sys.argv[2])
            unknown = json.loads(sys.argv[3])
            tolerance = float(sys.argv[4])
            result = compare_faces(known, unknown, tolerance)
            print(json.dumps(result))
    elif action == 'detect':
        if len(sys.argv) < 3:
            print(json.dumps({'success': False, 'error': 'No image path provided'}))
        else:
            result = detect_faces(sys.argv[2])
            print(json.dumps(result))
    elif action == 'live_process':
        if len(sys.argv) < 4:
            print(json.dumps({'success': False, 'error': 'Need image and known encodings'}))
        else:
            result = process_live_frame(sys.argv[2], sys.argv[3], float(sys.argv[4]) if len(sys.argv) > 4 else 0.6)
            print(json.dumps(result))
    elif action == 'antispoof':
        if len(sys.argv) < 3:
            print(json.dumps({'success': False, 'error': 'No image path provided'}))
        else:
            result = anti_spoofing_check(sys.argv[2])
            print(json.dumps(result))
    else:
        print(json.dumps({'success': False, 'error': f'Unknown action: {action}'}))
