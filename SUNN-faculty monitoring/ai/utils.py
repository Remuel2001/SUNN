#!/usr/bin/env python3
"""Utility functions for the AI face recognition module."""

import os
import json
import base64
from io import BytesIO
from PIL import Image
import numpy as np

def image_to_base64(image_path):
    """Convert image file to base64 string."""
    with open(image_path, 'rb') as f:
        data = f.read()
    return 'data:image/jpeg;base64,' + base64.b64encode(data).decode('utf-8')

def base64_to_image(base64_str):
    """Convert base64 string to PIL Image."""
    if ',' in base64_str:
        base64_str = base64_str.split(',')[1]
    data = base64.b64decode(base64_str)
    return Image.open(BytesIO(data))

def resize_image(image, max_size=640):
    """Resize image while maintaining aspect ratio."""
    w, h = image.size
    if max(w, h) <= max_size:
        return image
    ratio = max_size / max(w, h)
    new_size = (int(w * ratio), int(h * ratio))
    return image.resize(new_size, Image.LANCZOS)

def save_temp_image(image, prefix='temp'):
    """Save a temporary image and return the path."""
    temp_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'temp')
    os.makedirs(temp_dir, exist_ok=True)
    import uuid
    filename = f"{prefix}_{uuid.uuid4().hex[:8]}.jpg"
    path = os.path.join(temp_dir, filename)
    if isinstance(image, str):
        image = base64_to_image(image)
    image.save(path, 'JPEG', quality=85)
    return path

def get_known_encodings_from_db():
    """Fetch known face encodings from database via PHP API."""
    import subprocess
    result = subprocess.run([
        'php', '-r', '
            require_once "' + os.path.join(os.path.dirname(os.path.dirname(__file__)), 'config', 'config.php') + '";
            $db = getDB();
            $stmt = $db->query("SELECT instructor_id, face_encoding FROM facial_data WHERE status=\"active\"");
            $data = [];
            while ($row = $stmt->fetch()) {
                $data[] = ["instructor_id" => $row["instructor_id"], "encoding" => json_decode($row["face_encoding"])];
            }
            echo json_encode($data);
        '
    ], capture_output=True, text=True)
    return json.loads(result.stdout) if result.returncode == 0 else []
