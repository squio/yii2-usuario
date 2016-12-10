<?php

namespace Da\User\Form;

use Da\User\Factory\EmailChangeStrategyFactory;
use Da\User\Helper\SecurityHelper;
use Da\User\Model\User;
use Da\User\Traits\ContainerTrait;
use Da\User\Traits\ModuleTrait;
use Yii;
use yii\base\Model;

class SettingsForm extends Model
{
    use ModuleTrait;
    use ContainerTrait;

    /**
     * @var string
     */
    public $email;
    /**
     * @var string
     */
    public $username;
    /**
     * @var string
     */
    public $new_password;
    /**
     * @var string
     */
    public $current_password;
    /**
     * @var SecurityHelper
     */
    protected $securityHelper;

    /** @var User */
    private $user;

    public function __construct(SecurityHelper $securityHelper, array $config)
    {
        $this->securityHelper = $securityHelper;
        parent::__construct($config);
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'usernameRequired' => ['username', 'required'],
            'usernameTrim' => ['username', 'filter', 'filter' => 'trim'],
            'usernameLength' => ['username', 'string', 'min' => 3, 'max' => 255],
            'usernamePattern' => ['username', 'match', 'pattern' => '/^[-a-zA-Z0-9_\.@]+$/'],
            'emailRequired' => ['email', 'required'],
            'emailTrim' => ['email', 'filter', 'filter' => 'trim'],
            'emailPattern' => ['email', 'email'],
            'emailUsernameUnique' => [
                ['email', 'username'],
                'unique',
                'when' => function ($model, $attribute) {
                    return $this->user->$attribute != $model->$attribute;
                },
                'targetClass' => $this->getClassMap()[User::class]
            ],
            'newPasswordLength' => ['new_password', 'string', 'max' => 72, 'min' => 6],
            'currentPasswordRequired' => ['current_password', 'required'],
            'currentPasswordValidate' => [
                'current_password',
                function ($attribute) {
                    if (!$this->securityHelper->validatePassword($this->$attribute, $this->user->password_hash)) {
                        $this->addError($attribute, Yii::t('user', 'Current password is not valid'));
                    }
                }
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'email' => Yii::t('user', 'Email'),
            'username' => Yii::t('user', 'Username'),
            'new_password' => Yii::t('user', 'New password'),
            'current_password' => Yii::t('user', 'Current password'),
        ];
    }

    /**
     * @return User|null|\yii\web\IdentityInterface
     */
    public function getUser()
    {
        if ($this->user == null) {
            $this->user = Yii::$app->user->identity;
        }

        return $this->user;
    }

    /**
     * Saves new account settings.
     *
     * @return bool
     */
    public function save()
    {
        if ($this->validate()) {
            $this->user->scenario = 'settings';
            $this->user->username = $this->username;
            $this->user->password = $this->new_password;
            if ($this->email == $this->user->email && $this->user->unconfirmed_email != null) {
                $this->user->unconfirmed_email = null;

                return $this->user->save();

            } elseif ($this->email != $this->user->email) {
                $strategy = EmailChangeStrategyFactory::makeByStrategyType(
                    $this->getModule()->emailChangeStrategy,
                    $this
                );

                return $strategy->run();
            }

        }

        return false;
    }
}
