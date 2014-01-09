<?php
namespace Robinson\Backend\Models;
class ImageCategory extends \Phalcon\Mvc\Model
{
    protected $basePath;
    
    protected $imageCategoryId;
    
    protected $categoryId;
    
    protected $filename;
    
    protected $extension;
    
    protected $createdAt;
    
    protected $sort;
    
    /**
     * Initializion method.
     * 
     * @return void
     */
    public function initialize()
    {
        $this->setSource('ImageCategory');
        $this->belongsTo('categoryId', 'Robinson\Backend\Models\Category', 'categoryId');
    }
    
    /**
     * Listener for construct event, sets images path.
     * 
     * @return void
     */
    public function onConstruct()
    {
        if ($this->getDI()->has('config'))
        {
            $this->basePath = realpath($this->getDI()->getShared('config')->application->categoryImagesPath);
        }
    }
    
    /**
     * Gets sort order of image.
     * 
     * @return int
     */
    public function getSort()
    {
        return (int) $this->sort;
    }
    
    /**
     * Sets sort order of image.
     * 
     * @param int $sort sort order
     * 
     * @return \Robinson\Backend\Models\ImageCategory
     */
    public function setSort($sort)
    {
        $this->sort = (int) $sort;
        return $this;
    }
    
    /**
     * Sets base path.
     * 
     * @param string $basePath location where images are stored
     * 
     * @return \Robinson\Backend\Models\ImageCategory
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }
    
    /**
     * Creates and persists model from uploaded file.
     * 
     * @param \Phalcon\Http\Request\File $file       uploaded file
     * @param int                        $categoryId id
     * 
     * @return \Robinson\Backend\Models\ImageCategory
     */
    public function createFromUploadedFile(\Phalcon\Http\Request\File $file, $categoryId)
    { 
        if (!$this->basePath)
        {
            throw new \Phalcon\Mvc\Model\Exception('basePath is not set.');
        }

        /* @var $tag \Phalcon\Tag */
        $tag = $this->getDI()->getService('tag')->resolve();
        $this->filename = $tag->friendlyTitle(pathinfo($file->getName(), PATHINFO_FILENAME));
        $this->extension = pathinfo($file->getName(), PATHINFO_EXTENSION);
        $this->categoryId = $categoryId;
        if (!$this->save())
        {
            throw new \ErrorException('Unable to save ImageCategory model.');
        }
        
        if (!$file->moveTo($this->basePath . '/' . $this->getRealFilename()))
        {
            throw new \ErrorException(sprintf('Unable to move uploaded file to destination "%s".', 
                $this->basePath . '/' . $this->getRealFilename()));
        }
        
        return $this;
    }
    
    /**
     * Gets "real" filename of image, filesystem filename.
     * 
     * @return string
     */
    public function getRealFilename()
    {
        return $this->getImageCategoryId() . '-' . $this->filename . '.' . $this->extension;
    }
    
    /**
     * Get id.
     * 
     * @return int
     */
    public function getImageCategoryId()
    {
        return (int) $this->imageCategoryId;
    }
    
    /**
     * Saves image and sets its sort order and createdAt if one is not set.
     * 
     * @param mixed $data      inherited
     * @param mixed $whiteList inherited
     * 
     * @return bool
     */
    public function save($data = null, $whiteList = null)
    {
        if (null === $this->sort)
        {
            $this->sort = (int) self::maximum(array('categoryId = ' . $this->categoryId , 'column' => 'sort')) + 1;
        }
        
        if (null === $this->createdAt)
        {
            $this->createdAt = date('Y-m-d H:i:s');
        }
        
        return parent::save($data, $whiteList);
    }
    
    /**
     * Deletes image from db and filesystem.
     * 
     * @return bool
     * @throws \Phalcon\Mvc\Model\Exception if basePath is not set
     */
    public function delete()
    {
        if (!$this->basePath)
        {
            throw new \Phalcon\Mvc\Model\Exception('basePath is not set.');
        }
        
        if ($this->isFile($this->basePath . '/' . $this->getRealFilename()))
        {
            $this->unlink($this->basePath . '/' . $this->getRealFilename());
        }
        
        $dirIterator = new \DirectoryIterator($this->basePath);
        while ($dirIterator->valid())
        {
            if ($dirIterator->current()->isDir())
            {
                $crop = $this->basePath . '/' . $dirIterator->current()->getFilename() . '/' . $this->getRealFilename();
                
                if ($this->isFile($crop))
                {
                    $this->unlink($crop);
                }
            }
            
            $dirIterator->next();
        }
        
        return $this->parentDelete();
    }
    
    /**
     * Overriden to provide easier PHPUnit mocking.
     * 
     * @return bolean
     */
    public function parentDelete()
    {
        return parent::delete();
    }
    
    /**
     * Method which does image resizing, dimensions are sorted by folders with $width x $height names.
     * 
     * @param int $width  px
     * @param int $height px
     * 
     * @return string public path to image
     */
    public function getResizedSrc($width = 300, $height = 0)
    {
        $cropDir = $this->basePath . '/' . $width . 'x' . $height;
        
        if (!$this->isDir($cropDir))
        {
            $this->mkdir($cropDir);
        }
        
        $cropFile = $cropDir . '/' . $this->getRealFilename();
        
        $public = '/img/category/' . $width . 'x' . $height . '/' . $this->getRealFilename();
        
        if ($this->isFile($cropFile))
        {
            return $public;
        }

        $imagick = $this->getDI()->get('Imagick', array($this->basePath . '/' . $this->getRealFilename()));
        $imagick->scaleimage($width, $height);
        $imagick->writeimage($cropFile);
        return $public;
        
    }
    
    /**
     * is_file() wrapper method
     * 
     * @param type $file filepath
     * 
     * @return bool
     */
    protected function isFile($file)
    {
       return is_file($file); 
    }
    
    /**
     * unlink() wrapper
     * 
     * @param string $file filepath
     * 
     * @return bool
     */
    protected function unlink($file)
    {
        return unlink($file);
    }
    
    /**
     * is_dir() wrapper
     * 
     * @param string $filename filename
     * 
     * @return bool
     */
    protected function isDir($filename)
    {
        return is_dir($filename);
    }
    
    /**
     * mkdir() wrapper
     * 
     * @param string $pathname pathname
     * 
     * @return bool
     */
    protected function mkdir($pathname)
    {
        return mkdir($pathname, 0755);
    }
}