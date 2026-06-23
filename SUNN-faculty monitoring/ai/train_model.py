#!/usr/bin/env python3
"""
SUNN Faculty Monitoring - Face Model Training Script
Run this script to enroll/train face data for instructors.
Usage: python train_model.py <instructor_id> <image_folder_path>
"""

import sys
import os
import json
import face_recognition
import numpy as np

sys.path.insert(0, os.path.dirname(__file__))
from face_recognition_module import encode_face, ensure_dirs

def train_instructor(instructor_id, image_paths):
    """Train face encodings for a specific instructor from multiple images."""
    encodings = []
    for img_path in image_paths:
        if not os.path.exists(img_path):
            print(f"Warning: Image not found: {img_path}", file=sys.stderr)
            continue
        result = encode_face(img_path)
        if result['success']:
            encodings.append(result['encoding'])
            print(f"  ✓ Encoded: {img_path}", file=sys.stderr)
        else:
            print(f"  ✗ Failed: {img_path} - {result['error']}", file=sys.stderr)
    return encodings

def batch_train():
    """Batch train all instructors from directory structure."""
    ensure_dirs()
    faces_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'faces')
    if not os.path.exists(faces_dir):
        print(json.dumps({'success': False, 'error': 'Faces directory not found'}))
        return

    all_encodings = {}
    for instructor_id in os.listdir(faces_dir):
        instructor_dir = os.path.join(faces_dir, instructor_id)
        if not os.path.isdir(instructor_dir):
            continue
        image_files = [os.path.join(instructor_dir, f) for f in os.listdir(instructor_dir)
                      if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp'))]
        if not image_files:
            continue
        print(f"Training instructor {instructor_id}...", file=sys.stderr)
        encodings = train_instructor(instructor_id, image_files)
        if encodings:
            all_encodings[instructor_id] = encodings

    print(json.dumps({'success': True, 'instructors_trained': len(all_encodings), 'data': all_encodings}))

if __name__ == '__main__':
    ensure_dirs()
    if len(sys.argv) >= 3 and sys.argv[1] == 'train':
        instructor_id = sys.argv[2]
        images = sys.argv[3:] if len(sys.argv) > 3 else []
        if not images:
            print(json.dumps({'success': False, 'error': 'No images provided'}))
        else:
            encodings = train_instructor(instructor_id, images)
            print(json.dumps({'success': True, 'instructor_id': instructor_id, 'encodings_count': len(encodings), 'average_encoding': np.mean(encodings, axis=0).tolist() if encodings else None}))
    elif len(sys.argv) >= 2 and sys.argv[1] == 'batch':
        batch_train()
    else:
        print(json.dumps({'success': False, 'error': 'Usage: python train_model.py train <instructor_id> <image1> [image2...] | batch'}))
